<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

final class PersistanceEmployePostgresql
{
    /*
      PLAN (PersistanceEmployePostgresql) :

      1) Rôle du service
         - regrouper toutes les requêtes SQL utiles à l’espace employé
         - le contrôleur décide qui a le droit, ici on branche la BDD

      2) Listes à afficher
         a) Avis en attente (modération)
         b) Incidents ouverts (signalements)

      3) Actions employé de mise à jour 
         a) Valider / refuser un avis (statut_moderation + modérateur)
         b) Marquer un incident comme traité (incident_resolu)
    */

    public function __construct(private ConnexionPostgresql $connexion)
    {
    }

    /**
     * Lister les avis dont la modération est EN_ATTENTE.
     * - On récupère l’avis + le passager + les villes du covoiturage
     * - Limité pour éviter une liste trop longue
     *
     * @return array<int, array{
     *   id_avis:int,
     *   note:int,
     *   statut_moderation:string,
     *   date_depot:string,
     *   pseudo_passager:string,
     *   ville_depart:string,
     *   ville_arrivee:string
     * }>
     */
    public function listerAvisEnAttente(int $limite = 50): array
    {
        // 1) Connexion PDO 
        $pdo = $this->connexion->obtenirPdo();

        // 2) Avis en attente 
        $stmt = $pdo->prepare("
            SELECT
              a.id_avis,
              a.note,
              a.statut_moderation,
              to_char(a.date_depot, 'YYYY-MM-DD') AS date_depot,
              u_passager.pseudo AS pseudo_passager,
              c.ville_depart,
              c.ville_arrivee
            FROM avis a
            JOIN participation p ON p.id_participation = a.id_participation
            JOIN utilisateur u_passager ON u_passager.id_utilisateur = p.id_utilisateur
            JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
            WHERE a.statut_moderation = 'EN_ATTENTE'
            ORDER BY a.date_depot DESC
            LIMIT :limite
        ");

        // 3) Sécurité : la limite est un entier (évite une injection via LIMIT)
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $lignes */
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4) Normalisation 
        $resultat = [];
        foreach ($lignes as $ligne) {
            $resultat[] = [
                'id_avis' => (int) ($ligne['id_avis'] ?? 0),
                'note' => (int) ($ligne['note'] ?? 0),
                'statut_moderation' => (string) ($ligne['statut_moderation'] ?? ''),
                'date_depot' => (string) ($ligne['date_depot'] ?? ''),
                'pseudo_passager' => (string) ($ligne['pseudo_passager'] ?? ''),
                'ville_depart' => (string) ($ligne['ville_depart'] ?? ''),
                'ville_arrivee' => (string) ($ligne['ville_arrivee'] ?? ''),
            ];
        }

        return $resultat;
    }

    /**
     * Lister les covoiturages en statut INCIDENT non résolu.
     * - On récupère le chauffeur et optionnellement un passager lié
     * - On garde une structure stable pour l’affichage dans l’espace employé
     *
     * @return array<int, array{
     *   id_covoiturage:int,
     *   libelle:string,
     *   statut:string,
     *   date_depart:string,
     *   ville_depart:string,
     *   ville_arrivee:string,
     *   pseudo_passager:string,
     *   email_passager:string,
     *   pseudo_chauffeur:string,
     *   email_chauffeur:string
     * }>
     */
    public function listerIncidentsOuverts(int $limite = 50): array
    {
        $pdo = $this->connexion->obtenirPdo();

        /*
          - JOIN utilisateur u_ch : le chauffeur existe toujours (covoiturage -> id_utilisateur)
          - LEFT JOIN participation + utilisateur passager : il peut ne pas y avoir de passager
            (ou plusieurs, mais ici je prend la jointure telle quelle pour avoir du contexte)
        */
        $stmt = $pdo->prepare("
            SELECT
              c.id_covoiturage,
              COALESCE(c.incident_commentaire, 'Incident') AS libelle,
              c.statut_covoiturage AS statut,
              to_char(c.date_heure_depart, 'YYYY-MM-DD') AS date_depart,
              c.ville_depart,
              c.ville_arrivee,
              u_ch.pseudo AS pseudo_chauffeur,
              u_ch.email AS email_chauffeur,
              COALESCE(u_pa.pseudo, '') AS pseudo_passager,
              COALESCE(u_pa.email, '') AS email_passager
            FROM covoiturage c
            JOIN utilisateur u_ch ON u_ch.id_utilisateur = c.id_utilisateur
            LEFT JOIN participation p
              ON p.id_covoiturage = c.id_covoiturage
             AND p.est_annulee = false
            LEFT JOIN utilisateur u_pa ON u_pa.id_utilisateur = p.id_utilisateur
            WHERE c.statut_covoiturage = 'INCIDENT'
              AND c.incident_resolu = false
            ORDER BY c.date_heure_depart DESC
            LIMIT :limite
        ");

        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $lignes */
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultat = [];
        foreach ($lignes as $ligne) {
            $resultat[] = [
                'id_covoiturage' => (int) ($ligne['id_covoiturage'] ?? 0),
                'libelle' => (string) ($ligne['libelle'] ?? ''),
                'statut' => (string) ($ligne['statut'] ?? ''),
                'date_depart' => (string) ($ligne['date_depart'] ?? ''),
                'ville_depart' => (string) ($ligne['ville_depart'] ?? ''),
                'ville_arrivee' => (string) ($ligne['ville_arrivee'] ?? ''),
                'pseudo_chauffeur' => (string) ($ligne['pseudo_chauffeur'] ?? ''),
                'email_chauffeur' => (string) ($ligne['email_chauffeur'] ?? ''),
                'pseudo_passager' => (string) ($ligne['pseudo_passager'] ?? ''),
                'email_passager' => (string) ($ligne['email_passager'] ?? ''),
            ];
        }

        return $resultat;
    }

    /*
      Modération d’un avis :
      - décision attendue : VALIDE ou REFUSE
      - on n’autorise la modification que si l’avis est encore EN_ATTENTE
      - on enregistre l’employé modérateur
    */
    public function modererAvis(int $idAvis, string $decision, int $idEmploye): bool
    {
        // 1) On force une décision autorisée (garde-fou simple)
        $decision = $decision === 'VALIDE' ? 'VALIDE' : 'REFUSE';

        $pdo = $this->connexion->obtenirPdo();

        // 2) Mise à jour : uniquement si EN_ATTENTE
        $stmt = $pdo->prepare("
            UPDATE avis
            SET statut_moderation = :decision,
                id_employe_moderateur = :id_employe
            WHERE id_avis = :id_avis
              AND statut_moderation = 'EN_ATTENTE'
        ");

        $stmt->execute([
            'decision' => $decision,
            'id_employe' => $idEmploye,
            'id_avis' => $idAvis,
        ]);

        return $stmt->rowCount() > 0;
    }

    /*
      Marquer un incident comme traité :
      - on ne change que si l’incident est encore ouvert 
      - et si le covoiturage est bien en statut INCIDENT
    */
    public function marquerIncidentTraite(int $idCovoiturage): bool
    {
        $pdo = $this->connexion->obtenirPdo();

        $stmt = $pdo->prepare("
            UPDATE covoiturage
            SET incident_resolu = true
            WHERE id_covoiturage = :id
              AND statut_covoiturage = 'INCIDENT'
              AND incident_resolu = false
        ");

        $stmt->execute(['id' => $idCovoiturage]);

        return $stmt->rowCount() > 0;
    }
}
