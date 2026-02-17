<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

final class PersistanceCreditsPostgresql
{
    public function __construct(private ConnexionPostgresql $connexionPostgresql)
    {
    }

    public function obtenirSoldeCredits(int $idUtilisateur): int
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "SELECT credits FROM utilisateur WHERE id_utilisateur = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $idUtilisateur]);

        $credits = $stmt->fetchColumn();

        return $credits === false ? 0 : (int) $credits;
    }

    /**
     * Historique des crédits :
     * - Débit passager : réservation est donc une participation non annulée
     * - Gain chauffeur : participation validée OK est donc une commission déduite si présente
     *
     * @return array<int, array<string, mixed>>
     */
    public function listerOperationsCredits(int $idUtilisateur, int $limite = 50): array
    {
        $limite = max(1, min(200, $limite));
        $pdo = $this->connexionPostgresql->obtenirPdo();

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

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('id_utilisateur', $idUtilisateur, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
