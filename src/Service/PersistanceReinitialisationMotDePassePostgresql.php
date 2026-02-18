<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

final class PersistanceReinitialisationMotDePassePostgresql
{
    public function __construct(private ConnexionPostgresql $connexion)
    {
    }

    public function creerJetonPourEmail(string $email): ?string
    {
        $pdo = $this->connexion->obtenirPdo();

        $stmt = $pdo->prepare('SELECT id_utilisateur FROM utilisateur WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $idUtilisateur = $stmt->fetchColumn();

        if ($idUtilisateur === false) {
            return null; // appelant renvoie un message neutre
        }

        // Jeton en clair (pour l’e-mail) + hash stocké en base (sécurité)
        $jeton = bin2hex(random_bytes(32)); // 64 caractères hex
        $jetonHash = hash('sha256', $jeton);

        $stmt = $pdo->prepare("
            INSERT INTO reinitialisation_mot_de_passe (id_utilisateur, jeton_hash, date_expiration)
            VALUES (:id_utilisateur, :jeton_hash, now() + interval '30 minutes')
        ");
        $stmt->execute([
            'id_utilisateur' => (int) $idUtilisateur,
            'jeton_hash' => $jetonHash,
        ]);

        return $jeton;
    }

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

    public function utiliserJetonEtChangerMotDePasse(
        int $idReinitialisation,
        int $idUtilisateur,
        string $motDePasseClair
    ): void {
        $pdo = $this->connexion->obtenirPdo();

        // bcrypt => longueur 60 
        $hash = password_hash($motDePasseClair, PASSWORD_BCRYPT);

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
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
