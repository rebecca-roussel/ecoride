<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use RuntimeException;

final class PersistanceUtilisateurPostgresql
{
    /*
      PLAN (PersistanceUtilisateurPostgresql) :

      1) Rôle de ce service
         - ce fichier est mon “pont” entre Symfony et PostgreSQL
         - ici je mets les requêtes SQL liées aux utilisateurs
         - comme ça, mes contrôleurs restent propres (pas de SQL dedans)

      2) Principes
         - j’utilise PDO et des requêtes préparées (sécurité injection SQL)
         - je nettoie les entrées (trim) pour éviter les surprises
         - je m’appuie sur les contraintes de la BDD (UNIQUE, CHECK, etc.)

      3) Ce que je gère ici
         - création d’un utilisateur (inscription)
         - vérification pseudo/email déjà utilisés
         - récupération d’un utilisateur par email (connexion)
         - conversion photo_path -> URL affichable
         - données pour tableau de bord
         - données pour la page profil
         - mise à jour de la photo de profil
    */

    /*
      Valeurs “fixes” du projet
      - crédits de départ : 20 (cohérent avec le TP)
      - photo par défaut : avatar (dans public/icones)
    */
    private const CREDITS_DEPART = 20;
    private const URL_PHOTO_DEFAUT = '/icones/avatar.svg';

    public function __construct(private ConnexionPostgresql $connexionPostgresql)
    {
    }

    /**
     * Crée un utilisateur et retourne son id.
     *
     * Idée :
     * - je valide les bases côté PHP (champ obligatoire + au moins un rôle)
     * - puis j’insère
     * - si la base refuse (UNIQUE), je transforme en message compréhensible
     */
    public function creerUtilisateur(
        string $pseudo,
        string $email,
        string $motDePasseHash,
        bool $roleChauffeur,
        bool $rolePassager,
        ?string $photoPath = null,
    ): int {
        // Nettoyage minimal : je ne veux pas enregistrer "  toto  "
        $pseudoNettoye = trim($pseudo);
        $emailNettoye = trim($email);

        // Sécurité simple : champs obligatoires
        if ($pseudoNettoye === '' || $emailNettoye === '') {
            throw new RuntimeException('Pseudo et email sont obligatoires.');
        }

        // Sécurité métier : un utilisateur doit avoir au moins un rôle
        // (cohérent avec ck_utilisateur_au_moins_un_role, role_interne reste false ici)
        if (!$roleChauffeur && !$rolePassager) {
            throw new RuntimeException('Il faut choisir au moins un rôle : chauffeur ou passager.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
          Insertion
          - credits : on force à 20 au départ
          - role_interne : false (un utilisateur “normal” n’est pas un employé/admin)
          - statut : ACTIF au départ
          - photo_path : peut être null (photo optionnelle)
        */
        $sql = "
            INSERT INTO utilisateur (
                pseudo, email, mot_de_passe_hash,
                credits,
                role_chauffeur, role_passager, role_interne,
                photo_path, statut, date_changement_statut
            )
            VALUES (
                :pseudo, :email, :mot_de_passe_hash,
                :credits,
                :role_chauffeur, :role_passager, false,
                :photo_path, 'ACTIF', NULL
            )
            RETURNING id_utilisateur
        ";

        try {
            $requete = $pdo->prepare($sql);
            $requete->execute([
                'pseudo' => $pseudoNettoye,
                'email' => $emailNettoye,
                'mot_de_passe_hash' => $motDePasseHash,
                'credits' => self::CREDITS_DEPART,
                'role_chauffeur' => $roleChauffeur,
                'role_passager' => $rolePassager,
                'photo_path' => $photoPath,
            ]);
        } catch (PDOException $exception) {
            // PostgreSQL : violation de contrainte UNIQUE (pseudo/email) = code 23505
            if ($exception->getCode() === '23505') {
                throw new RuntimeException('Pseudo ou email déjà utilisé.', 0, $exception);
            }

            // Sinon je relance l’exception (problème “technique”)
            throw $exception;
        }

        // RETURNING id_utilisateur : je récupère l'id créé
        $id = $requete->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("Impossible de récupérer l'id_utilisateur après insertion.");
        }

        return (int) $id;
    }

    /*
      Vérifie si un pseudo existe déjà
      - je renvoie true si je trouve au moins une ligne
      - LIMIT 1 : inutile d’aller plus loin
    */
    public function pseudoExisteDeja(string $pseudo): bool
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $requete = $pdo->prepare('
            SELECT 1
            FROM utilisateur
            WHERE pseudo = :pseudo
            LIMIT 1
        ');
        $requete->execute(['pseudo' => trim($pseudo)]);

        return $requete->fetchColumn() !== false;
    }

    /*
      Vérifie si un email existe déjà
      - même logique que pseudoExisteDeja
    */
    public function emailExisteDeja(string $email): bool
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $requete = $pdo->prepare('
            SELECT 1
            FROM utilisateur
            WHERE email = :email
            LIMIT 1
        ');
        $requete->execute(['email' => trim($email)]);

        return $requete->fetchColumn() !== false;
    }

    /*
      Trouve un utilisateur par email
      - utile pour la connexion
      - je récupère mot_de_passe_hash pour vérifier password_verify() côté contrôleur
    */
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

        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        return $ligne === false ? null : $ligne;
    }

    /*
      Transforme un chemin stocké en BDD en URL affichable dans le navigateur

      Exemples :
      - null -> /icones/avatar.svg (photo par défaut)
      - "photos/profil_xxx.jpg" -> "/photos/profil_xxx.jpg"
      - " /photos/xxx.jpg " -> "/photos/xxx.jpg" (nettoyé)
    */
    public function urlPhotoProfil(?string $photoPath): string
    {
        if (null === $photoPath) {
            return self::URL_PHOTO_DEFAUT;
        }

        $photoPathNettoye = trim($photoPath);
        if ($photoPathNettoye === '') {
            return self::URL_PHOTO_DEFAUT;
        }

        // Je garantis un chemin web qui commence par "/"
        return '/' . ltrim($photoPathNettoye, '/');
    }

    /*
      Données minimales pour le tableau de bord
      - je force role_chauffeur et role_passager en int (0 ou 1) pour simplifier le Twig
      - je garde photo_path pour afficher la photo
    */
    public function obtenirDonneesTableauDeBord(int $idUtilisateur): ?array
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
            SELECT
                id_utilisateur,
                pseudo,
                credits,
                role_chauffeur::int AS role_chauffeur,
                role_passager::int AS role_passager,
                photo_path
            FROM utilisateur
            WHERE id_utilisateur = :id_utilisateur
            LIMIT 1
        ";

        $requete = $pdo->prepare($sql);
        $requete->execute(['id_utilisateur' => $idUtilisateur]);

        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        return $ligne === false ? null : $ligne;
    }

    /*
      Données pour la page "Gérer mon profil"
      - on récupère l'essentiel : pseudo, email, statut, photo
    */
    public function obtenirDonneesProfil(int $idUtilisateur): ?array
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
            SELECT
                id_utilisateur,
                pseudo,
                email,
                statut,
                photo_path
            FROM utilisateur
            WHERE id_utilisateur = :id_utilisateur
            LIMIT 1
        ";

        $requete = $pdo->prepare($sql);
        $requete->execute(['id_utilisateur' => $idUtilisateur]);

        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        return $ligne === false ? null : $ligne;
    }

    /*
      Met à jour la photo de profil
      - je stocke un chemin relatif du type "photos/profil_xxx.jpg"
      - l'affichage passera par urlPhotoProfil() pour fabriquer l'URL web
    */
    public function mettreAJourPhotoProfil(int $idUtilisateur, string $photoPath): void
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
            UPDATE utilisateur
            SET photo_path = :photo_path
            WHERE id_utilisateur = :id_utilisateur
        ";

        $requete = $pdo->prepare($sql);
        $requete->execute([
            'photo_path' => $photoPath,
            'id_utilisateur' => $idUtilisateur,
        ]);
    }

    /*
      Met à jour les rôles "chauffeur" et "passager" d’un utilisateur

      Objectif :
      - page "Gérer mes rôles" : l’utilisateur choisit ses rôles, puis on enregistre en BDD.

      Sécurité métier :
      - l’utilisateur ne doit pas pouvoir décocher les deux rôles en même temps
      - je bloque côté PHP avec un message clair
      - et la BDD a aussi sa contrainte (ck_utilisateur_au_moins_un_role) en filet de sécurité
    */
    public function mettreAJourRoles(int $idUtilisateur, bool $roleChauffeur, bool $rolePassager): void
    {
        if (!$roleChauffeur && !$rolePassager) {
            throw new RuntimeException('Il faut garder au moins un rôle : chauffeur ou passager.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
            UPDATE utilisateur
            SET role_passager = :role_passager,
            role_chauffeur = :role_chauffeur
            WHERE id_utilisateur = :id_utilisateur
            ";

        try {
            $requete = $pdo->prepare($sql);

            // Important : on force le type boolean côté PDO (évite les '' / 'on' / etc.)
            $requete->bindValue('role_passager', $rolePassager, \PDO::PARAM_BOOL);
            $requete->bindValue('role_chauffeur', $roleChauffeur, \PDO::PARAM_BOOL);
            $requete->bindValue('id_utilisateur', $idUtilisateur, \PDO::PARAM_INT);

            $requete->execute();
        } catch (PDOException $exception) {
            // Si jamais la BDD refuse (contrainte métier), je renvoie un message compréhensible.
            // Exemple : ck_utilisateur_au_moins_un_role si on essayait de mettre les 2 à false.
            if ($exception->getCode() === '23514') {
                throw new RuntimeException('Vous devez garder au moins un rôle : chauffeur ou passager.', 0, $exception);
            }

            throw $exception;
        }
    }
}

