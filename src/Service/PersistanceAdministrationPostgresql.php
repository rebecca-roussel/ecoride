<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Service de persistance PostgreSQL pour l'espace administrateur.
 *
 * Cette classe regroupe les lectures et écritures SQL
 * utilisées par le parcours administrateur.
 *
 * Son rôle couvre principalement :
 * - la lecture des indicateurs de synthèse ;
 * - la liste des comptes ;
 * - la création d'un employé ;
 * - la suspension d'un compte ;
 * - la réactivation d'un compte ;
 * - la préparation de séries de données pour les graphiques.
 *
 * La connexion PostgreSQL est fournie par le service ConnexionPostgresql.
 * La classe reste donc centrée sur les requêtes SQL
 * et sur la transformation des résultats en tableaux simples
 * exploitables par les contrôleurs.
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
     * Les données renvoyées alimentent les tuiles de synthèse
     * affichées dans l'interface administrateur.
     *
     * Les indicateurs calculés sont :
     * - le nombre d'utilisateurs actifs hors comptes internes ;
     * - le nombre d'employés actifs ;
     * - le nombre total de covoiturages publiés ;
     * - le total des crédits générés par les commissions.
     *
     * La lecture des crédits générés vérifie d'abord
     * si la table commission_plateforme existe.
     * Ce garde-fou permet de conserver un retour exploitable
     * même si cette table n'est pas encore présente dans la base.
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
        /*
         * On récupère d'abord la connexion PDO centralisée
         * afin d'exécuter les différentes lectures SQL.
         */
        $pdo = $this->connexion->obtenirPdo();

        /*
         * Lecture du nombre d'utilisateurs actifs.
         *
         * Les comptes internes sont exclus
         * pour garder uniquement les comptes utilisateurs classiques.
         */
        $stmt = $pdo->query("
            SELECT COUNT(*) AS nb
            FROM utilisateur u
            WHERE u.statut = 'ACTIF'
              AND COALESCE(u.role_interne, false) = false
        ");
        $nbUtilisateursActifs = (int) ($stmt->fetchColumn() ?: 0);

        /*
         * Lecture du nombre d'employés actifs.
         *
         * On passe par la table employe
         * puis on vérifie le statut du compte utilisateur lié.
         */
        $stmt = $pdo->query("
            SELECT COUNT(*) AS nb
            FROM employe e
            JOIN utilisateur u ON u.id_utilisateur = e.id_utilisateur
            WHERE u.statut = 'ACTIF'
        ");
        $nbEmployesActifs = (int) ($stmt->fetchColumn() ?: 0);

        /*
         * Lecture du nombre total de covoiturages publiés.
         */
        $stmt = $pdo->query("SELECT COUNT(*) AS nb FROM covoiturage");
        $nbCovoituragesPublies = (int) ($stmt->fetchColumn() ?: 0);

        /*
         * Le total des crédits générés est initialisé à zéro.
         * Cette valeur sera remplacée seulement
         * si la table commission_plateforme existe.
         */
        $creditsGeneres = 0;

        /*
         * Vérification de l'existence de la table commission_plateforme.
         *
         * Cette étape évite une erreur SQL
         * dans un environnement où la table n'est pas encore créée.
         */
        $stmt = $pdo->query("
            SELECT EXISTS (
              SELECT 1
              FROM information_schema.tables
              WHERE table_schema = 'public'
                AND table_name = 'commission_plateforme'
            ) AS existe
        ");
        $existeCommission = (bool) $stmt->fetchColumn();

        /*
         * Si la table existe, on additionne les crédits de commission.
         */
        if ($existeCommission) {
            $stmt = $pdo->query("
                SELECT COALESCE(SUM(cp.credits_commission), 0) AS total
                FROM commission_plateforme cp
            ");
            $creditsGeneres = (int) ($stmt->fetchColumn() ?: 0);
        }

        /*
         * On renvoie un tableau simple
         * déjà prêt à être exploité côté contrôleur.
         */
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
     * de l'identifiant utilisateur dans les tables administrateur et employe.
     *
     * La méthode renvoie des données simplifiées
     * pour l'affichage dans l'interface :
     * identifiant, pseudo, rôle lisible et statut.
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
        /*
         * On récupère la connexion PDO centralisée.
         */
        $pdo = $this->connexion->obtenirPdo();

        /*
         * La requête prépare une liste triée par pseudo.
         *
         * Le rôle affiché est calculé avec CASE :
         * - ADMIN si l'utilisateur est présent dans administrateur ;
         * - EMPLOYE si l'utilisateur est présent dans employe ;
         * - UTILISATEUR dans les autres cas.
         */
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

        /*
         * On transforme le résultat brut SQL
         * en tableau homogène avec cast explicite des valeurs.
         */
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
     * La création repose sur une transaction PostgreSQL.
     * Le but est de garantir un traitement cohérent :
     * - insertion dans la table utilisateur ;
     * - insertion dans la table employe.
     *
     * Si une étape échoue, la transaction est annulée.
     * On évite ainsi un compte créé partiellement.
     *
     * Le mot de passe reçu par cette méthode
     * est déjà attendu sous forme de hash.
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
        /*
         * On ouvre la connexion PDO,
         * puis on démarre une transaction SQL.
         */
        $pdo = $this->connexion->obtenirPdo();
        $pdo->beginTransaction();

        try {
            /*
             * Création du compte dans la table utilisateur.
             *
             * Le compte interne est créé avec :
             * - zéro crédit ;
             * - aucun rôle chauffeur ;
             * - aucun rôle passager ;
             * - role_interne à true ;
             * - statut ACTIF.
             */
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

            /*
             * On récupère l'identifiant PostgreSQL du compte créé.
             */
            $idUtilisateur = (int) $stmt->fetchColumn();

            /*
             * Ce garde-fou protège contre une insertion anormale
             * qui ne renverrait aucun identifiant exploitable.
             */
            if ($idUtilisateur <= 0) {
                throw new RuntimeException('Création utilisateur impossible.');
            }

            /*
             * Deuxième écriture :
             * on rattache le compte à la table employe.
             */
            $stmt2 = $pdo->prepare("INSERT INTO employe (id_utilisateur) VALUES (:id)");
            $stmt2->execute(['id' => $idUtilisateur]);

            /*
             * Les deux écritures ont réussi,
             * on valide donc la transaction.
             */
            $pdo->commit();

            return $idUtilisateur;
        } catch (Throwable $e) {
            /*
             * Si une erreur survient,
             * on annule la transaction
             * pour revenir à un état cohérent.
             */
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Suspend un compte utilisateur puis renvoie son pseudo.
     *
     * Le pseudo est lu avant la mise à jour
     * afin de pouvoir afficher un message plus lisible côté interface.
     *
     * Si la requête UPDATE ne touche aucune ligne,
     * la méthode renvoie null.
     *
     * @param int $idUtilisateur Identifiant du compte à suspendre.
     *
     * @return string|null Pseudo du compte suspendu, ou null si aucun compte n'a été trouvé.
     */
    public function suspendreCompteParId(int $idUtilisateur): ?string
    {
        /*
         * On récupère la connexion PDO.
         */
        $pdo = $this->connexion->obtenirPdo();

        /*
         * Lecture préalable du pseudo
         * pour pouvoir le réutiliser ensuite dans l'interface.
         */
        $stmtPseudo = $pdo->prepare("SELECT pseudo FROM utilisateur WHERE id_utilisateur = :id");
        $stmtPseudo->execute(['id' => $idUtilisateur]);
        $pseudoCible = (string) ($stmtPseudo->fetchColumn() ?: '');

        /*
         * Mise à jour du statut vers SUSPENDU
         * avec horodatage du changement.
         */
        $stmt = $pdo->prepare("
            UPDATE utilisateur
            SET statut = 'SUSPENDU',
                date_changement_statut = NOW()
            WHERE id_utilisateur = :id
        ");
        $stmt->execute(['id' => $idUtilisateur]);

        /*
         * Si aucune ligne n'a été modifiée,
         * cela signifie qu'aucun compte correspondant
         * n'a été trouvé pour cette opération.
         */
        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $pseudoCible;
    }

    /**
     * Réactive un compte utilisateur puis renvoie son pseudo.
     *
     * Le fonctionnement suit la même logique que la suspension :
     * lecture du pseudo, mise à jour du statut,
     * puis retour de null si aucune ligne n'a été touchée.
     *
     * @param int $idUtilisateur Identifiant du compte à réactiver.
     *
     * @return string|null Pseudo du compte réactivé, ou null si aucun compte n'a été trouvé.
     */
    public function reactiverCompteParId(int $idUtilisateur): ?string
    {
        /*
         * On récupère la connexion PDO.
         */
        $pdo = $this->connexion->obtenirPdo();

        /*
         * Lecture préalable du pseudo
         * pour le réutiliser ensuite dans l'interface.
         */
        $stmtPseudo = $pdo->prepare("SELECT pseudo FROM utilisateur WHERE id_utilisateur = :id");
        $stmtPseudo->execute(['id' => $idUtilisateur]);
        $pseudoCible = (string) ($stmtPseudo->fetchColumn() ?: '');

        /*
         * Mise à jour du statut vers ACTIF
         * avec nouvelle date de changement.
         */
        $stmt = $pdo->prepare("
            UPDATE utilisateur
            SET statut = 'ACTIF',
                date_changement_statut = NOW()
            WHERE id_utilisateur = :id
        ");
        $stmt->execute(['id' => $idUtilisateur]);

        /*
         * Si aucune ligne n'a été modifiée,
         * on renvoie null.
         */
        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $pseudoCible;
    }

    /**
     * Récupère le nombre de covoiturages par jour sur une période récente.
     *
     * La série de jours est générée en SQL avec generate_series.
     * Ce choix permet de renvoyer une ligne pour chaque jour,
     * même quand aucun covoiturage n'existe à cette date.
     *
     * Les covoiturages terminés sont exclus de ce comptage.
     *
     * @param int $nbJours Nombre de jours à inclure.
     *
     * @return array<int, array{jour:string, nb:int}>
     *   - jour : `YYYY-MM-DD`
     *   - nb   : nombre de covoiturages hors statut `TERMINE`
     */
    public function obtenirCovoituragesParJour(int $nbJours = 14): array
    {
        /*
         * On encadre la période entre 1 et 31 jours
         * pour éviter une valeur incohérente
         * ou trop lourde à traiter.
         */
        $nbJours = max(1, min(31, $nbJours));

        /*
         * On récupère la connexion PDO.
         */
        $pdo = $this->connexion->obtenirPdo();

        /*
         * La sous-requête generate_series fabrique la série de dates.
         * On réalise ensuite un LEFT JOIN avec covoiturage
         * afin de garder un jour visible même sans trajet.
         */
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

        /*
         * On reformate les lignes SQL
         * en tableau homogène pour la couche supérieure.
         */
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
     * Si la table commission_plateforme n'existe pas,
     * la méthode renvoie quand même une série de jours
     * avec des valeurs à zéro.
     *
     * Cela permet à l'interface de conserver
     * une structure de données stable pour son graphique.
     *
     * @param int $nbJours Nombre de jours à inclure.
     *
     * @return array<int, array{jour:string, credits:int}>
     *   - jour : `YYYY-MM-DD`
     *   - credits : total des crédits de commission pour ce jour
     */
    public function obtenirCreditsParJour(int $nbJours = 14): array
    {
        /*
         * On récupère la connexion PDO.
         */
        $pdo = $this->connexion->obtenirPdo();

        /*
         * Vérification préalable de l'existence
         * de la table commission_plateforme.
         */
        $stmt = $pdo->query("
            SELECT EXISTS (
              SELECT 1
              FROM information_schema.tables
              WHERE table_schema = 'public'
                AND table_name = 'commission_plateforme'
            ) AS existe
        ");
        $existeCommission = (bool) $stmt->fetchColumn();

        /*
         * Requête principale :
         * on génère la série des jours,
         * puis on agrège les crédits de commission par date.
         */
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

        /*
         * Si la table existe, on peut exécuter la requête complète.
         */
        if ($existeCommission) {
            $stmt->execute();
        } else {
            /*
             * Si la table n'existe pas,
             * on renvoie quand même une série de jours
             * avec zéro crédit pour chaque date.
             *
             * Cette branche garde une structure de retour stable
             * pour le graphique côté interface.
             */
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

        /*
         * On reformate les lignes SQL
         * en tableau simple exploitable par le contrôleur.
         */
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