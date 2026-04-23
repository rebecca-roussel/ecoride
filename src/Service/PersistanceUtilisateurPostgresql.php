<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Service de persistance PostgreSQL pour les utilisateurs.
 *
 * Cette classe regroupe les requêtes SQL liées à la table `utilisateur`
 * et aux informations directement nécessaires au parcours utilisateur.
 *
 * Son rôle reste centré sur la base relationnelle :
 * préparer les requêtes, exécuter les lectures et écritures,
 * puis renvoyer des données déjà exploitables par les contrôleurs.
 *
 * Le SQL est concentré ici pour éviter de le disperser dans les contrôleurs.
 * Cela garde une séparation plus claire entre :
 * le parcours HTTP, la session, et les accès à PostgreSQL.
 *
 * @package App\Service
 */
final class PersistanceUtilisateurPostgresql
{
    /**
     * Nombre de crédits attribués au moment de l'inscription.
     *
     * Cette valeur fixe évite de répéter le nombre dans plusieurs méthodes.
     *
     * @var int
     */
    private const CREDITS_DEPART = 20;

    /**
     * URL de la photo de profil par défaut.
     *
     * Cette valeur est utilisée si aucun chemin photo n'est disponible.
     *
     * @var string
     */
    private const URL_PHOTO_DEFAUT = '/icones/avatar.svg';

    /**
     * Initialise le service avec la connexion PostgreSQL.
     *
     * `ConnexionPostgresql` fournit l'objet PDO utilisé ensuite
     * pour exécuter les requêtes SQL.
     *
     * @param ConnexionPostgresql $connexionPostgresql Service de connexion à PostgreSQL.
     */
    public function __construct(private ConnexionPostgresql $connexionPostgresql)
    {
    }

    /**
     * Crée un utilisateur puis retourne son identifiant.
     *
     * Cette méthode prépare les données minimales nécessaires à l'inscription :
     * pseudo, email, hash du mot de passe, rôles publics et photo éventuelle.
     *
     * Les contrôles simples utiles avant insertion sont faits ici :
     * pseudo obligatoire, email obligatoire,
     * et au moins un rôle public actif.
     *
     * L'instruction `RETURNING id_utilisateur` permet à PostgreSQL
     * de renvoyer immédiatement l'identifiant généré
     * sans lancer une seconde requête.
     *
     * @param string $pseudo Pseudo saisi lors de l'inscription.
     * @param string $email E-mail saisi lors de l'inscription.
     * @param string $motDePasseHash Mot de passe déjà haché avant l'appel.
     * @param bool $roleChauffeur Indique si le rôle chauffeur a été choisi.
     * @param bool $rolePassager Indique si le rôle passager a été choisi.
     * @param string|null $photoPath Chemin relatif de la photo, ou null.
     *
     * @return int Identifiant PostgreSQL de l'utilisateur créé.
     *
     * @throws RuntimeException Si les données minimales sont invalides
     *                          ou si le pseudo / l'e-mail sont déjà utilisés.
     * @throws PDOException Si PostgreSQL renvoie une erreur technique non gérée ici.
     */
    public function creerUtilisateur(
        string $pseudo,
        string $email,
        string $motDePasseHash,
        bool $roleChauffeur,
        bool $rolePassager,
        ?string $photoPath = null,
    ): int {
        /*
         * `trim()` retire les espaces autour des chaînes.
         * Cela évite d'enregistrer des valeurs remplies seulement en apparence.
         */
        $pseudoNettoye = trim($pseudo);
        $emailNettoye = trim($email);

        if ($pseudoNettoye === '' || $emailNettoye === '') {
            throw new RuntimeException('Pseudo et email sont obligatoires.');
        }

        if (!$roleChauffeur && !$rolePassager) {
            throw new RuntimeException('Il faut choisir au moins un rôle : chauffeur ou passager.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
         * Le compte utilisateur est créé ici comme compte public :
         * - crédits de départ ;
         * - rôles chauffeur / passager ;
         * - role_interne à false ;
         * - statut initial ACTIF ;
         * - photo optionnelle.
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

            /*
             * `bindValue()` permet de lier chaque valeur au bon paramètre SQL
             * avec le type attendu par PDO.
             *
             * Le typage booléen est important pour les colonnes de rôles.
             */
            $requete->bindValue('pseudo', $pseudoNettoye, PDO::PARAM_STR);
            $requete->bindValue('email', $emailNettoye, PDO::PARAM_STR);
            $requete->bindValue('mot_de_passe_hash', $motDePasseHash, PDO::PARAM_STR);
            $requete->bindValue('credits', self::CREDITS_DEPART, PDO::PARAM_INT);
            $requete->bindValue('role_chauffeur', (bool) $roleChauffeur, PDO::PARAM_BOOL);
            $requete->bindValue('role_passager', (bool) $rolePassager, PDO::PARAM_BOOL);

            if ($photoPath === null) {
                $requete->bindValue('photo_path', null, PDO::PARAM_NULL);
            } else {
                $requete->bindValue('photo_path', $photoPath, PDO::PARAM_STR);
            }

            $requete->execute();
        } catch (PDOException $exception) {
            /*
             * Le code PostgreSQL `23505` correspond à une contrainte UNIQUE violée.
             * Ici, cela signifie qu'un pseudo ou un e-mail existe déjà.
             */
            if ($exception->getCode() === '23505') {
                throw new RuntimeException('Pseudo ou email déjà utilisé.', 0, $exception);
            }

            throw $exception;
        }

        $id = $requete->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("Impossible de récupérer l'id_utilisateur après insertion.");
        }

        return (int) $id;
    }

    /**
     * Vérifie si un pseudo existe déjà.
     *
     * La requête utilise `SELECT 1` et `LIMIT 1`
     * car on veut seulement savoir si une ligne existe,
     * pas charger un enregistrement complet.
     *
     * @param string $pseudo Pseudo à vérifier.
     *
     * @return bool True si le pseudo existe déjà, false sinon.
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

    /**
     * Vérifie si un e-mail existe déjà.
     *
     * Le principe est le même que pour le pseudo :
     * on cherche seulement l'existence d'une ligne.
     *
     * @param string $email E-mail à vérifier.
     *
     * @return bool True si l'e-mail existe déjà, false sinon.
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

    /**
     * Recherche un utilisateur par son e-mail.
     *
     * Cette lecture sert à récupérer les données générales d'un utilisateur
     * sans les indicateurs calculés employé / administrateur.
     *
     * @param string $email E-mail recherché.
     *
     * @return array<string, mixed>|null
     *         Tableau associatif si l'utilisateur existe, null sinon.
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

    /**
     * Recherche un utilisateur avec les données utiles au parcours de connexion.
     *
     * Cette méthode renvoie :
     * - l'identifiant ;
     * - le pseudo ;
     * - le hash du mot de passe ;
     * - le statut ;
     * - les rôles chauffeur et passager ;
     * - un indicateur `est_employe` ;
     * - un indicateur `est_administrateur`.
     *
     * Les deux indicateurs sont calculés avec `EXISTS`.
     * `EXISTS` permet de répondre à une question simple :
     * est-ce qu'une ligne correspondante existe dans la table ciblée ?
     *
     * Cette méthode prépare donc exactement les données attendues
     * par le parcours de connexion sans laisser de SQL dans le contrôleur.
     *
     * @param string $email Adresse e-mail utilisée pour la connexion.
     *
     * @return array<string, mixed>|null
     *         Tableau associatif si un utilisateur correspond, null sinon.
     */
    public function trouverUtilisateurPourConnexionParEmail(string $email): ?array
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $requete = $pdo->prepare('
            SELECT
                u.id_utilisateur,
                u.pseudo,
                u.mot_de_passe_hash,
                u.statut,
                u.role_chauffeur,
                u.role_passager,
                EXISTS (
                    SELECT 1
                    FROM employe e
                    WHERE e.id_utilisateur = u.id_utilisateur
                ) AS est_employe,
                EXISTS (
                    SELECT 1
                    FROM administrateur a
                    WHERE a.id_utilisateur = u.id_utilisateur
                ) AS est_administrateur
            FROM utilisateur u
            WHERE u.email = :email
            LIMIT 1
        ');

        $requete->execute(['email' => trim($email)]);

        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        return $ligne === false ? null : $ligne;
    }

    /**
     * Transforme un chemin stocké en base en URL affichable.
     *
     * Si aucun chemin n'existe, la photo par défaut est renvoyée.
     *
     * @param string|null $photoPath Chemin stocké en base.
     *
     * @return string URL utilisable dans l'interface.
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

        return '/' . ltrim($photoPathNettoye, '/');
    }

    /**
     * Récupère les données minimales utiles au tableau de bord.
     *
     * Les rôles chauffeur et passager sont convertis en entier dans SQL
     * pour simplifier leur usage côté Twig.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur.
     *
     * @return array<string, mixed>|null
     *         Tableau associatif si l'utilisateur existe, null sinon.
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

    /**
     * Récupère les données utiles à la page profil.
     *
     * Cette lecture se limite aux champs nécessaires à l'affichage du profil.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur.
     *
     * @return array<string, mixed>|null
     *         Tableau associatif si l'utilisateur existe, null sinon.
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

    /**
     * Met à jour le chemin de la photo de profil.
     *
     * Cette méthode écrit seulement le chemin en base.
     * Le fichier image lui-même est géré ailleurs.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur.
     * @param string $photoPath Nouveau chemin photo.
     *
     * @return void
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

    /**
     * Met à jour les rôles chauffeur et passager d'un utilisateur.
     *
     * La règle métier impose de garder au moins un rôle public actif.
     * Cette règle est vérifiée ici côté PHP
     * puis protégée aussi par la base de données.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur concerné.
     * @param bool $roleChauffeur Nouvelle valeur du rôle chauffeur.
     * @param bool $rolePassager Nouvelle valeur du rôle passager.
     *
     * @return void
     *
     * @throws RuntimeException Si les deux rôles sont désactivés.
     * @throws PDOException Si PostgreSQL renvoie une erreur technique non gérée ici.
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

            /*
             * Le typage booléen est explicitement forcé ici
             * pour que PostgreSQL reçoive bien les bonnes valeurs.
             */
            $requete->bindValue('role_passager', $rolePassager, PDO::PARAM_BOOL);
            $requete->bindValue('role_chauffeur', $roleChauffeur, PDO::PARAM_BOOL);
            $requete->bindValue('id_utilisateur', $idUtilisateur, PDO::PARAM_INT);

            $requete->execute();
        } catch (PDOException $exception) {
            /*
             * Le code `23514` correspond à une contrainte CHECK violée.
             * Ici, cela rejoint la règle métier qui impose au moins un rôle.
             */
            if ($exception->getCode() === '23514') {
                throw new RuntimeException('Vous devez garder au moins un rôle : chauffeur ou passager.', 0, $exception);
            }

            throw $exception;
        }
    }
}