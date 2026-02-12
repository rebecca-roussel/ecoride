<?php

declare(strict_types=1);

namespace App\Service;

final class PersistanceUtilisateurPostgresql
{
    public function __construct(private ConnexionPostgresql $connexionPostgresql)
    {
    }

    /**
     * Crée un utilisateur et retourne son id.
     * Lève une exception si pseudo/email déjà pris (contrainte UNIQUE).
     */
    public function creerUtilisateur(
        string $pseudo,
        string $email,
        string $motDePasseHash,
        bool $roleChauffeur,
        bool $rolePassager,
        ?string $photoPath = null,
    ): int {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
            INSERT INTO utilisateur (
                pseudo, email, mot_de_passe_hash,
                credits,
                role_chauffeur, role_passager, role_interne,
                photo_path, statut, date_changement_statut
            )
            VALUES (
                :pseudo, :email, :mot_de_passe_hash,
                20,
                :role_chauffeur, :role_passager, false,
                :photo_path, 'ACTIF', NULL
            )
            RETURNING id_utilisateur
        ";

        $requete = $pdo->prepare($sql);
        $requete->execute([
            'pseudo' => trim($pseudo),
            'email' => trim($email),
            'mot_de_passe_hash' => $motDePasseHash,
            'role_chauffeur' => $roleChauffeur,
            'role_passager' => $rolePassager,
            'photo_path' => $photoPath,
        ]);

        $id = $requete->fetchColumn();

        return (int) $id;
    }

    public function pseudoExisteDeja(string $pseudo): bool
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $requete = $pdo->prepare('
            SELECT 1
            FROM utilisateur
            WHERE pseudo = :pseudo
            LIMIT 1
        ');
        $requete->execute(['pseudo' => $pseudo]);

        return false !== $requete->fetchColumn();
    }

    public function emailExisteDeja(string $email): bool
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $requete = $pdo->prepare('
            SELECT 1
            FROM utilisateur
            WHERE email = :email
            LIMIT 1
        ');
        $requete->execute(['email' => $email]);

        return false !== $requete->fetchColumn();
    }
    public function trouverParEmail(string $email): ?array
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $requete = $pdo->prepare('
        SELECT
            id_utilisateur,
            pseudo,
            email,
            mot_de_passe_hash,
            credits,
            role_chauffeur,
            role_passager,
            role_interne,
            photo_path,
            statut
        FROM utilisateur
        WHERE email = :email
        LIMIT 1
    ');

        $requete->execute(['email' => trim($email)]);

        $ligne = $requete->fetch(\PDO::FETCH_ASSOC);

        return $ligne === false ? null : $ligne;
    }
}
