<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Service de persistance du parcours de réinitialisation du mot de passe.
 *
 * Ce fichier regroupe les accès PostgreSQL liés à ce parcours :
 * création d'une demande de réinitialisation,
 * lecture d'un jeton encore valide,
 * puis mise à jour effective du mot de passe.
 *
 * Le service ne gère pas l'affichage,
 * ni les messages utilisateur,
 * ni l'envoi du courriel.
 * Son rôle reste centré sur la base de données.
 */
final class PersistanceReinitialisationMotDePassePostgresql
{
    public function __construct(private ConnexionPostgresql $connexion)
    {
    }

    /**
     * Crée une demande de réinitialisation pour un e-mail existant.
     *
     * Le jeton envoyé par courriel n'est jamais stocké en clair en base.
     * Seul son hash SHA-256 est enregistré.
     *
     * Si aucun compte ne correspond à l'e-mail,
     * la méthode renvoie `null`.
     *
     * @param string $email Adresse e-mail saisie dans le formulaire.
     *
     * @return array{id_utilisateur:int, jeton:string}|null
     */
    public function creerJetonPourEmail(string $email): ?array
    {
        $email = trim($email);

        $pdo = $this->connexion->obtenirPdo();

        /*
         * On cherche d'abord l'utilisateur associé à l'e-mail.
         * Si aucun compte n'existe, on renvoie `null`.
         */
        $stmt = $pdo->prepare('
            SELECT id_utilisateur
            FROM utilisateur
            WHERE email = :email
            LIMIT 1
        ');
        $stmt->execute(['email' => $email]);

        $idUtilisateur = $stmt->fetchColumn();

        if ($idUtilisateur === false) {
            return null;
        }

        /*
         * Le jeton en clair sert uniquement à fabriquer le lien envoyé par courriel.
         * La base stocke seulement son hash, pour éviter de conserver le jeton brut.
         */
        $jeton = bin2hex(random_bytes(32));
        $jetonHash = hash('sha256', $jeton);

        $stmt = $pdo->prepare("
            INSERT INTO reinitialisation_mot_de_passe (
                id_utilisateur,
                jeton_hash,
                date_expiration
            )
            VALUES (
                :id_utilisateur,
                :jeton_hash,
                now() + interval '30 minutes'
            )
        ");
        $stmt->execute([
            'id_utilisateur' => (int) $idUtilisateur,
            'jeton_hash' => $jetonHash,
        ]);

        return [
            'id_utilisateur' => (int) $idUtilisateur,
            'jeton' => $jeton,
        ];
    }

    /**
     * Retrouve un jeton encore valide.
     *
     * Un jeton valide doit respecter trois conditions :
     * il doit exister,
     * ne pas avoir déjà été utilisé,
     * et ne pas avoir expiré.
     *
     * @param string $jeton Jeton reçu dans l'URL.
     *
     * @return array{id_reinitialisation:int,id_utilisateur:int}|null
     */
    public function trouverJetonValide(string $jeton): ?array
    {
        $pdo = $this->connexion->obtenirPdo();
        $jetonHash = hash('sha256', $jeton);

        $stmt = $pdo->prepare("
            SELECT id_reinitialisation, id_utilisateur
            FROM reinitialisation_mot_de_passe
            WHERE jeton_hash = :jeton_hash
              AND date_utilisation IS NULL
              AND date_expiration > now()
            LIMIT 1
        ");
        $stmt->execute(['jeton_hash' => $jetonHash]);

        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne !== false ? $ligne : null;
    }

    /**
     * Utilise une demande de réinitialisation et remplace le mot de passe.
     *
     * Cette méthode réalise deux mises à jour dans une même transaction :
     * le mot de passe de l'utilisateur,
     * puis la date d'utilisation du jeton.
     *
     * Une transaction permet de grouper plusieurs opérations SQL
     * pour qu'elles réussissent ensemble ou échouent ensemble.
     *
     * @param int $idReinitialisation Identifiant de la demande de réinitialisation.
     * @param int $idUtilisateur Identifiant de l'utilisateur concerné.
     * @param string $motDePasseClair Nouveau mot de passe saisi.
     *
     * @return void
     */
    public function utiliserJetonEtChangerMotDePasse(
        int $idReinitialisation,
        int $idUtilisateur,
        string $motDePasseClair
    ): void {
        $pdo = $this->connexion->obtenirPdo();

        /*
         * bcrypt produit ici le hash qui sera enregistré en base.
         * Le mot de passe en clair n'est donc pas stocké tel quel.
         */
        $hash = password_hash($motDePasseClair, PASSWORD_BCRYPT);

        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Impossible de sécuriser le mot de passe.');
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                UPDATE utilisateur
                SET mot_de_passe_hash = :hash
                WHERE id_utilisateur = :id_utilisateur
            ");
            $stmt->execute([
                'hash' => $hash,
                'id_utilisateur' => $idUtilisateur,
            ]);

            $stmt = $pdo->prepare("
                UPDATE reinitialisation_mot_de_passe
                SET date_utilisation = now()
                WHERE id_reinitialisation = :id_reinitialisation
            ");
            $stmt->execute([
                'id_reinitialisation' => $idReinitialisation,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}