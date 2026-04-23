<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Service de persistance PostgreSQL pour les crédits utilisateur.
 *
 * Cette classe regroupe les lectures SQL liées au solde de crédits
 * et à l'historique des mouvements visibles dans l'espace utilisateur.
 *
 * Son rôle est de :
 * - récupérer le solde actuel d'un utilisateur ;
 * - reconstituer un historique lisible des opérations de crédits.
 *
 * La connexion PostgreSQL est fournie
 * par le service ConnexionPostgresql.
 */
final class PersistanceCreditsPostgresql
{
    /**
     * Initialise le service avec la connexion PostgreSQL.
     *
     * @param ConnexionPostgresql $connexionPostgresql Service qui fournit l'accès PDO.
     */
    public function __construct(private ConnexionPostgresql $connexionPostgresql)
    {
    }

    /**
     * Récupère le solde actuel de crédits d'un utilisateur.
     *
     * La méthode lit directement la colonne credits
     * dans la table utilisateur.
     *
     * Si aucun enregistrement n'est trouvé,
     * la méthode renvoie 0.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur concerné.
     *
     * @return int Solde actuel de crédits.
     */
    public function obtenirSoldeCredits(int $idUtilisateur): int
    {
        /*
         * On récupère la connexion PDO centralisée
         * pour exécuter la lecture SQL.
         */
        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
         * La requête lit le solde de crédits
         * du compte utilisateur demandé.
         *
         * LIMIT 1 garde une lecture simple et explicite.
         */
        $sql = "SELECT credits FROM utilisateur WHERE id_utilisateur = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $idUtilisateur]);

        /*
         * fetchColumn() renvoie la première colonne de la première ligne.
         * Si aucune ligne n'est trouvée, la valeur renvoyée est false.
         */
        $credits = $stmt->fetchColumn();

        /*
         * Si aucun solde n'est trouvé, on renvoie 0.
         * Sinon, on convertit la valeur en entier.
         */
        return $credits === false ? 0 : (int) $credits;
    }

    /**
     * Liste les opérations de crédits à afficher dans l'historique.
     *
     * La méthode reconstitue deux types de mouvements :
     * - le débit côté passager, quand une participation est confirmée
     *   et non annulée ;
     * - le gain côté chauffeur, quand une participation est validée OK
     *   et que la commission éventuelle a été déduite.
     *
     * Le résultat renvoyé est trié du plus récent au plus ancien.
     *
     * La limite est encadrée entre 1 et 200
     * pour éviter une valeur incohérente ou trop lourde.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur concerné.
     * @param int $limite Nombre maximum de lignes à renvoyer.
     *
     * @return array<int, array<string, mixed>> Historique simplifié des mouvements de crédits.
     */
    public function listerOperationsCredits(int $idUtilisateur, int $limite = 50): array
    {
        /*
         * On encadre la limite entre 1 et 200
         * pour garder une requête raisonnable.
         */
        $limite = max(1, min(200, $limite));

        /*
         * On récupère la connexion PDO centralisée.
         */
        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
         * La requête fusionne deux lectures avec UNION ALL.
         *
         * Première partie :
         * on reconstruit les débits passager à partir des participations
         * non annulées. Le montant est négatif,
         * car il s'agit d'un débit de crédits.
         *
         * Deuxième partie :
         * on reconstruit les gains chauffeur à partir des participations
         * validées OK. La commission plateforme éventuelle est déduite
         * du montant gagné.
         *
         * Les deux ensembles sont ensuite triés
         * par date décroissante.
         */
        $sql = "
            (
                SELECT
                    p.date_heure_confirmation AS date_mouvement,
                    'Participation' AS libelle,
                    -p.credits_utilises AS montant_credits,
                    c.id_covoiturage,
                    c.ville_depart,
                    c.ville_arrivee
                FROM participation p
                JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
                WHERE p.id_utilisateur = :id_utilisateur
                  AND p.est_annulee = false
            )
            UNION ALL
            (
                SELECT
                    COALESCE(cp.date_commission, p.date_heure_confirmation) AS date_mouvement,
                    'Gain chauffeur' AS libelle,
                    (p.credits_utilises - COALESCE(cp.credits_commission, 0)) AS montant_credits,
                    c.id_covoiturage,
                    c.ville_depart,
                    c.ville_arrivee
                FROM participation p
                JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
                LEFT JOIN commission_plateforme cp ON cp.id_participation = p.id_participation
                WHERE c.id_utilisateur = :id_utilisateur
                  AND p.statut_validation = 'OK'
                  AND p.est_annulee = false
            )
            ORDER BY date_mouvement DESC
            LIMIT :limite
        ";

        /*
         * Préparation et exécution de la requête
         * avec typage explicite des paramètres entiers.
         */
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('id_utilisateur', $idUtilisateur, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        /*
         * fetchAll(PDO::FETCH_ASSOC) renvoie un tableau associatif.
         * Si aucune ligne n'est trouvée, on renvoie un tableau vide.
         */
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}