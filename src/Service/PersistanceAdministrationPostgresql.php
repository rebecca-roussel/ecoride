<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Service de persistance PostgreSQL pour l'espace administrateur.
 *
 * Cette classe regroupe les requêtes SQL au parcours administrateur :
 * lecture des statistiques, liste des comptes,
 * création d'un employé, suspension d'un compte et réactivation d'un compte.
 *
 * @package App\Service
 */
final class PersistanceAdministrationPostgresql
{
    /**
     * Initialise le service avec la connexion PostgreSQL.
     *
     * @param ConnexionPostgresql $connexion Service qui fournit l'accès PDO.
     */
    public function __construct(private ConnexionPostgresql $connexion)
    {
    }

    /**
     * Récupère les indicateurs globaux de l'espace administrateur.
     *
     * Les données renvoyées servent aux tuiles de synthèse (UI)
     * qui sont des pastilles d'information qui transforment des données 
     * en chiffres simples faciles à lire pour l'administrateur:
     * - utilisateurs actifs hors comptes internes ;
     * - employés actifs ;
     * - nombre total de covoiturages ;
     * - crédits générés par les commissions.
     *
     * La lecture des crédits générés vérifie d'abord l'existence
     * de la table `commission_plateforme`.
     * Cela permet de garder une réponse,
     * même si la table n'est pas encore présente.
     *
     * @return array{
     *   nb_utilisateurs_actifs:int,
     *   nb_employes_actifs:int,
     *   nb_covoiturages_publies:int,
     *   credits_generes:int
     * }
     */
    public function obtenirStats(): array
    {
        $pdo = $this->connexion->obtenirPdo();

        $stmt = $pdo->query("
            SELECT COUNT(*) AS nb
            FROM utilisateur u
            WHERE u.statut = 'ACTIF'
              AND COALESCE(u.role_interne, false) = false
        ");
        $nbUtilisateursActifs = (int) ($stmt->fetchColumn() ?: 0);

        $stmt = $pdo->query("
            SELECT COUNT(*) AS nb
            FROM employe e
            JOIN utilisateur u ON u.id_utilisateur = e.id_utilisateur
            WHERE u.statut = 'ACTIF'
        ");
        $nbEmployesActifs = (int) ($stmt->fetchColumn() ?: 0);

        $stmt = $pdo->query("SELECT COUNT(*) AS nb FROM covoiturage");
        $nbCovoituragesPublies = (int) ($stmt->fetchColumn() ?: 0);

        $creditsGeneres = 0;

        $stmt = $pdo->query("
            SELECT EXISTS (
              SELECT 1
              FROM information_schema.tables
              WHERE table_schema = 'public'
                AND table_name = 'commission_plateforme'
            ) AS existe
        ");
        $existeCommission = (bool) $stmt->fetchColumn();

        if ($existeCommission) {
            $stmt = $pdo->query("
                SELECT COALESCE(SUM(cp.credits_commission), 0) AS total
                FROM commission_plateforme cp
            ");
            $creditsGeneres = (int) ($stmt->fetchColumn() ?: 0);
        }

        return [
            'nb_utilisateurs_actifs' => $nbUtilisateursActifs,
            'nb_employes_actifs' => $nbEmployesActifs,
            'nb_covoiturages_publies' => $nbCovoituragesPublies,
            'credits_generes' => $creditsGeneres,
        ];
    }

    /**
     * Liste les comptes à afficher dans l'espace administrateur.
     *
     * Le rôle affiché est calculé à partir de la présence
     * dans les tables `administrateur` et `employe`.
     *
     * @param int $limite Nombre maximum de comptes à renvoyer.
     *
     * @return array<int, array{
     *   id_utilisateur:int,
     *   pseudo:string,
     *   role:string,
     *   statut:string
     * }>
     */
    public function listerComptes(int $limite = 50): array
    {
        $pdo = $this->connexion->obtenirPdo();

        $stmt = $pdo->prepare("
            SELECT
              u.id_utilisateur,
              u.pseudo,
              u.statut,
              CASE
                WHEN EXISTS (SELECT 1 FROM administrateur a WHERE a.id_utilisateur = u.id_utilisateur) THEN 'ADMIN'
                WHEN EXISTS (SELECT 1 FROM employe e WHERE e.id_utilisateur = u.id_utilisateur) THEN 'EMPLOYE'
                ELSE 'UTILISATEUR'
              END AS role
            FROM utilisateur u
            ORDER BY u.pseudo ASC
            LIMIT :limite
        ");
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $lignes */
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $comptes = [];
        foreach ($lignes as $ligne) {
            $comptes[] = [
                'id_utilisateur' => (int) ($ligne['id_utilisateur'] ?? 0),
                'pseudo' => (string) ($ligne['pseudo'] ?? ''),
                'role' => (string) ($ligne['role'] ?? 'UTILISATEUR'),
                'statut' => (string) ($ligne['statut'] ?? 'ACTIF'),
            ];
        }

        return $comptes;
    }

    /**
     * Crée un nouveau compte employé dans le système.
     *
     * Sécurité du tout ou rien (Transaction) :
     * Pour créer un employé, le logiciel doit faire deux étapes :
     * 1. L'inscrire dans la liste générale des utilisateurs.
     * 2. L'inscrire dans la liste spécifique des employés.
     *
     * Ce mécanisme garantit que si une étape échoue, l'autre est annulée.
     * On évite ainsi d'avoir un compte créé à moitié qui ferait bugger le site.
     *
     * Sécurité des données :
     * - Le mot de passe arrive déjà transformé en code illisible (hash).
     * - Le compte est réglé par défaut comme "ACTIF".
     *
     *
     * @param string $pseudo Pseudo du nouvel employé.
     * @param string $email E-mail du nouvel employé.
     * @param string $motDePasseHash Hash du mot de passe déjà préparé.
     *
     * @return int Identifiant PostgreSQL du compte créé.
     *
     * @throws Throwable Si l'une des deux écritures échoue.
     */
    public function creerEmploye(string $pseudo, string $email, string $motDePasseHash): int
    {
        $pdo = $this->connexion->obtenirPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO utilisateur (
                    pseudo, email, mot_de_passe_hash,
                    credits, role_chauffeur, role_passager, role_interne, statut
                )
                VALUES (
                    :pseudo, :email, :hash,
                    0, false, false, true, 'ACTIF'
                )
                RETURNING id_utilisateur
            ");
            $stmt->execute([
                'pseudo' => trim($pseudo),
                'email' => trim($email),
                'hash' => $motDePasseHash,
            ]);

            $idUtilisateur = (int) $stmt->fetchColumn();

            if ($idUtilisateur <= 0) {
                throw new RuntimeException('Création utilisateur impossible.');
            }

            $stmt2 = $pdo->prepare("INSERT INTO employe (id_utilisateur) VALUES (:id)");
            $stmt2->execute(['id' => $idUtilisateur]);

            $pdo->commit();

            return $idUtilisateur;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Suspend un compte utilisateur puis renvoie son pseudo.
     *
     * Le pseudo est lu avant la mise à jour afin de pouvoir afficher
     * un message plus lisible côté interface.
     *
     * Si aucune ligne n'est mise à jour, la méthode renvoie `null`.
     *
     * @param int $idUtilisateur Identifiant du compte à suspendre.
     *
     * @return string|null Pseudo du compte suspendu, ou null si aucun compte n'a été trouvé.
     */
    public function suspendreCompteParId(int $idUtilisateur): ?string
    {
        $pdo = $this->connexion->obtenirPdo();

        $stmtPseudo = $pdo->prepare("SELECT pseudo FROM utilisateur WHERE id_utilisateur = :id");
        $stmtPseudo->execute(['id' => $idUtilisateur]);
        $pseudoCible = (string) ($stmtPseudo->fetchColumn() ?: '');

        $stmt = $pdo->prepare("
            UPDATE utilisateur
            SET statut = 'SUSPENDU',
                date_changement_statut = NOW()
            WHERE id_utilisateur = :id
        ");
        $stmt->execute(['id' => $idUtilisateur]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $pseudoCible;
    }

    /**
     * Réactive un compte utilisateur puis renvoie son pseudo.
     *
     * Le fonctionnement est identique à la suspension :
     * lecture du pseudo, mise à jour du statut,
     * puis retour de `null` si aucune ligne n'a été touchée.
     *
     * @param int $idUtilisateur Identifiant du compte à réactiver.
     *
     * @return string|null Pseudo du compte réactivé, ou null si aucun compte n'a été trouvé.
     */
    public function reactiverCompteParId(int $idUtilisateur): ?string
    {
        $pdo = $this->connexion->obtenirPdo();

        $stmtPseudo = $pdo->prepare("SELECT pseudo FROM utilisateur WHERE id_utilisateur = :id");
        $stmtPseudo->execute(['id' => $idUtilisateur]);
        $pseudoCible = (string) ($stmtPseudo->fetchColumn() ?: '');

        $stmt = $pdo->prepare("
            UPDATE utilisateur
            SET statut = 'ACTIF',
                date_changement_statut = NOW()
            WHERE id_utilisateur = :id
        ");
        $stmt->execute(['id' => $idUtilisateur]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $pseudoCible;
    }

    /**
     * Récupère le nombre de covoiturages par jour sur une période récente.
     *
     * La série de jours est générée en SQL avec `generate_series`.
     * Cela permet de renvoyer une ligne pour chaque jour,
     * même quand aucun covoiturage n'existe à cette date.
     *
     * @param int $nbJours Nombre de jours à inclure.
     *
     * @return array<int, array{jour:string, nb:int}>
     *   - jour : `YYYY-MM-DD`
     *   - nb   : nombre de covoiturages hors statut `TERMINE`
     */
    public function obtenirCovoituragesParJour(int $nbJours = 14): array
    {
        $nbJours = max(1, min(31, $nbJours));

        $pdo = $this->connexion->obtenirPdo();

        $sql = "
            SELECT
              to_char(j.jour, 'YYYY-MM-DD') AS jour,
              COUNT(c.id_covoiturage) AS nb
            FROM (
              SELECT generate_series(
                CURRENT_DATE - (:nb_jours - 1) * INTERVAL '1 day',
                CURRENT_DATE,
                INTERVAL '1 day'
              )::date AS jour
            ) AS j
            LEFT JOIN covoiturage AS c
              ON c.date_heure_depart::date = j.jour
             AND c.statut_covoiturage <> 'TERMINE'
            GROUP BY j.jour
            ORDER BY j.jour ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('nb_jours', $nbJours, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $lignes */
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultat = [];
        foreach ($lignes as $ligne) {
            $resultat[] = [
                'jour' => (string) ($ligne['jour'] ?? ''),
                'nb' => (int) ($ligne['nb'] ?? 0),
            ];
        }

        return $resultat;
    }

    /**
     * Récupère les crédits générés par jour sur une période récente.
     *
     * Si la table `commission_plateforme` n'existe pas,
     * la méthode renvoie quand même une série de jours
     * avec des valeurs à zéro.
     *
     * @param int $nbJours Nombre de jours à inclure.
     *
     * @return array<int, array{jour:string, credits:int}>
     *   - jour : `YYYY-MM-DD`
     *   - credits : total des crédits de commission pour ce jour
     */
    public function obtenirCreditsParJour(int $nbJours = 14): array
    {
        $pdo = $this->connexion->obtenirPdo();

        $stmt = $pdo->query("
            SELECT EXISTS (
              SELECT 1
              FROM information_schema.tables
              WHERE table_schema = 'public'
                AND table_name = 'commission_plateforme'
            ) AS existe
        ");
        $existeCommission = (bool) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
              to_char(j.jour, 'YYYY-MM-DD') AS jour,
              COALESCE(SUM(cp.credits_commission), 0) AS credits
            FROM (
              SELECT generate_series(
                (CURRENT_DATE - (:nb_jours - 1) * INTERVAL '1 day')::date,
                CURRENT_DATE::date,
                INTERVAL '1 day'
              )::date AS jour
            ) j
            LEFT JOIN commission_plateforme cp
              ON cp.date_commission::date = j.jour
            GROUP BY j.jour
            ORDER BY j.jour ASC
        ");

        $stmt->bindValue('nb_jours', $nbJours, PDO::PARAM_INT);

        if ($existeCommission) {
            $stmt->execute();
        } else {
            $stmtJours = $pdo->prepare("
                SELECT to_char(j.jour, 'YYYY-MM-DD') AS jour
                FROM (
                  SELECT generate_series(
                    (CURRENT_DATE - (:nb_jours - 1) * INTERVAL '1 day')::date,
                    CURRENT_DATE::date,
                    INTERVAL '1 day'
                  )::date AS jour
                ) j
                ORDER BY j.jour ASC
            ");
            $stmtJours->bindValue('nb_jours', $nbJours, PDO::PARAM_INT);
            $stmtJours->execute();

            /** @var array<int, array<string, mixed>> $jours */
            $jours = $stmtJours->fetchAll(PDO::FETCH_ASSOC);

            $resultat = [];
            foreach ($jours as $ligne) {
                $resultat[] = [
                    'jour' => (string) ($ligne['jour'] ?? ''),
                    'credits' => 0,
                ];
            }

            return $resultat;
        }

        /** @var array<int, array<string, mixed>> $lignes */
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultat = [];
        foreach ($lignes as $ligne) {
            $resultat[] = [
                'jour' => (string) ($ligne['jour'] ?? ''),
                'credits' => (int) ($ligne['credits'] ?? 0),
            ];
        }

        return $resultat;
    }
}