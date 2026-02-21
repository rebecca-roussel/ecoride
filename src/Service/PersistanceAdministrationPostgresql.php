<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

final class PersistanceAdministrationPostgresql
{
    /*
      PLAN (PersistanceAdministrationPostgresql) :

      1) Rôle
         - Centraliser les requêtes PostgreSQL pour l’espace administrateur
         - Éviter de mettre du SQL dans le contrôleur

      2) Fonctions
         - obtenirStats()  : tuiles en haut (utilisateurs actifs, employés actifs, covoiturages, crédits)
         - listerComptes() : tableau des comptes (pseudo, rôle, statut)
    */

    public function __construct(private ConnexionPostgresql $connexion)
    {
    }

    /**
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

        // 1) Utilisateurs actifs (hors comptes internes)
        $stmt = $pdo->query("
            SELECT COUNT(*) AS nb
            FROM utilisateur u
            WHERE u.statut = 'ACTIF'
              AND COALESCE(u.role_interne, false) = false
        ");
        $nbUtilisateursActifs = (int) ($stmt->fetchColumn() ?: 0);

        // 2) Employés actifs (table employe + utilisateur ACTIF)
        $stmt = $pdo->query("
            SELECT COUNT(*) AS nb
            FROM employe e
            JOIN utilisateur u ON u.id_utilisateur = e.id_utilisateur
            WHERE u.statut = 'ACTIF'
        ");
        $nbEmployesActifs = (int) ($stmt->fetchColumn() ?: 0);

        // 3) Covoiturages publiés 
        $stmt = $pdo->query("SELECT COUNT(*) AS nb FROM covoiturage");
        $nbCovoituragesPublies = (int) ($stmt->fetchColumn() ?: 0);

        // 4) Crédits générés : commission_plateforme si la table existe
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
            WHEN EXISTS (SELECT 1 FROM employe e       WHERE e.id_utilisateur = u.id_utilisateur) THEN 'EMPLOYE'
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
 * @return array<int, array{jour:string, nb:int}>
 *   - jour : 'YYYY-MM-DD'
 *   - nb   : nombre de covoiturages (par jour de départ) hors TERMINE
 */
public function obtenirCovoituragesParJour(int $nbJours = 14): array
{
    $nbJours = max(1, min(31, $nbJours));

    $pdo = $this->connexion->obtenirPdo();

    $sql = "
        SELECT
          to_char(j.jour, 'YYYY-MM-DD') AS jour,
          COUNT(c.id_covoiturage)       AS nb
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
    $stmt->bindValue('nb_jours', $nbJours, \PDO::PARAM_INT);
    $stmt->execute();

    /** @var array<int, array<string, mixed>> $lignes */
    $lignes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $resultat = [];
    foreach ($lignes as $ligne) {
        $resultat[] = [
            'jour' => (string) ($ligne['jour'] ?? ''),
            'nb'   => (int) ($ligne['nb'] ?? 0),
        ];
    }

    return $resultat;
}


/**
 * @return array<int, array{jour:string, credits:int}>
 *   - jour : 'YYYY-MM-DD'
 *   - credits : total crédits commission ce jour-là
 *
 * Remarque : si commission_plateforme n’existe pas, on renvoie des zéros.
 */
public function obtenirCreditsParJour(int $nbJours = 14): array
{
    $pdo = $this->connexion->obtenirPdo();

    // Vérif existence table
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
        // Pas de table : on simule une réponse cohérente (jours + 0)
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
