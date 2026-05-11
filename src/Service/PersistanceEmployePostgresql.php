<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Service de persistance PostgreSQL pour l'espace employé.
 *
 * Cette classe regroupe les requêtes SQL utiles au parcours employé :
 * lecture des avis en attente, lecture des incidents ouverts,
 * récupération du détail d'un avis,
 * récupération du détail d'un incident,
 * modération d'un avis et marquage d'un incident comme traité.
 *
 * Une persistance est une classe qui sert de point d'accès à la base de données.
 * Son rôle est de préparer les requêtes SQL, de les exécuter,
 * puis de renvoyer des données déjà prêtes à être exploitées
 * par le contrôleur.
 *
 * Le contrôleur garde donc la gestion du web,
 * tandis que cette classe garde les accès à PostgreSQL.
 *
 * @package App\Service
 */
final class PersistanceEmployePostgresql
{
    /**
     * Initialise le service avec la connexion PostgreSQL.
     *
     * @param ConnexionPostgresql $connexion Service qui fournit l'objet PDO.
     */
    public function __construct(private ConnexionPostgresql $connexion)
    {
    }

    /**
     * Liste les avis encore en attente de modération.
     *
     * La requête récupère ici :
     * - l'identifiant et la note de l'avis ;
     * - son statut de modération ;
     * - la date de dépôt ;
     * - le pseudo du passager ;
     * - la ville de départ et la ville d'arrivée du covoiturage.
     *
     * `LIMIT` permet de borner le nombre de lignes renvoyées.
     * Cela évite de charger une liste trop longue d'un seul coup.
     *
     * @param int $limite Nombre maximum de lignes à renvoyer.
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
        $pdo = $this->connexion->obtenirPdo();

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

        /*
         * `bindValue()` associe ici la valeur `limite` au paramètre SQL.
         * Le type entier est forcé explicitement pour garder une requête sûre
         * même avec `LIMIT`.
         */
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $lignes */
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /*
         * La normalisation transforme les valeurs lues dans une structure stable.
         * Le contrôleur et Twig reçoivent ainsi toujours les mêmes clés
         * avec des types plus clairs.
         */
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
     * Liste les incidents encore ouverts.
     *
     * La requête cherche les covoiturages :
     * - en statut `INCIDENT` ;
     * - avec `incident_resolu = false`.
     *
     * `LEFT JOIN` signifie ici que la ligne principale doit rester présente
     * même si aucun passager n'est trouvé.
     * Cela permet d'afficher quand même l'incident
     * même si aucun participant actif n'est lié au covoiturage.
     *
     * @param int $limite Nombre maximum de lignes à renvoyer.
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

    /**
     * Recherche le détail d'un avis encore en attente.
     *
     * Cette lecture sert à afficher une page de détail plus complète :
     * avis, passager, chauffeur, covoiturage et date de départ.
     *
     * Si l'identifiant reçu est invalide ou si aucune ligne ne correspond,
     * la méthode renvoie `null`.
     *
     * @param int $idAvis Identifiant de l'avis.
     *
     * @return array{
     *   id_avis:int,
     *   note:int,
     *   commentaire:string,
     *   statut_moderation:string,
     *   date_depot:string,
     *   pseudo_passager:string,
     *   email_passager:string,
     *   pseudo_chauffeur:string,
     *   email_chauffeur:string,
     *   id_covoiturage:int,
     *   date_depart:string,
     *   ville_depart:string,
     *   ville_arrivee:string
     * }|null
     */
    public function trouverAvisEnAttenteParId(int $idAvis): ?array
    {
        if ($idAvis <= 0) {
            return null;
        }

        $pdo = $this->connexion->obtenirPdo();

        $stmt = $pdo->prepare("
            SELECT
              a.id_avis,
              a.note,
              COALESCE(a.commentaire, '') AS commentaire,
              a.statut_moderation,
              to_char(a.date_depot, 'YYYY-MM-DD HH24:MI') AS date_depot,
              u_pa.pseudo AS pseudo_passager,
              u_pa.email AS email_passager,
              u_ch.pseudo AS pseudo_chauffeur,
              u_ch.email AS email_chauffeur,
              c.id_covoiturage,
              to_char(c.date_heure_depart, 'YYYY-MM-DD HH24:MI') AS date_depart,
              c.ville_depart,
              c.ville_arrivee
            FROM avis a
            JOIN participation p ON p.id_participation = a.id_participation
            JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
            JOIN utilisateur u_pa ON u_pa.id_utilisateur = p.id_utilisateur
            JOIN utilisateur u_ch ON u_ch.id_utilisateur = c.id_utilisateur
            WHERE a.id_avis = :id_avis
              AND a.statut_moderation = 'EN_ATTENTE'
            LIMIT 1
        ");
        $stmt->execute(['id_avis' => $idAvis]);

        /** @var array<string, mixed>|false $ligne */
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ligne)) {
            return null;
        }

        return [
            'id_avis' => (int) ($ligne['id_avis'] ?? 0),
            'note' => (int) ($ligne['note'] ?? 0),
            'commentaire' => (string) ($ligne['commentaire'] ?? ''),
            'statut_moderation' => (string) ($ligne['statut_moderation'] ?? ''),
            'date_depot' => (string) ($ligne['date_depot'] ?? ''),
            'pseudo_passager' => (string) ($ligne['pseudo_passager'] ?? ''),
            'email_passager' => (string) ($ligne['email_passager'] ?? ''),
            'pseudo_chauffeur' => (string) ($ligne['pseudo_chauffeur'] ?? ''),
            'email_chauffeur' => (string) ($ligne['email_chauffeur'] ?? ''),
            'id_covoiturage' => (int) ($ligne['id_covoiturage'] ?? 0),
            'date_depart' => (string) ($ligne['date_depart'] ?? ''),
            'ville_depart' => (string) ($ligne['ville_depart'] ?? ''),
            'ville_arrivee' => (string) ($ligne['ville_arrivee'] ?? ''),
        ];
    }

    /**
     * Recherche le détail d'un incident encore ouvert.
     *
     * Cette méthode lit d'abord les données principales du covoiturage,
     * puis charge séparément la liste des passagers actifs.
     *
     * Cette séparation garde une lecture plus simple :
     * - une première requête pour l'incident ;
     * - une seconde pour les passagers.
     *
     * @param int $idCovoiturage Identifiant du covoiturage concerné.
     *
     * @return array{
     *   id_covoiturage:int,
     *   libelle:string,
     *   incident_commentaire:string,
     *   statut:string,
     *   date_depart:string,
     *   date_arrivee:string,
     *   ville_depart:string,
     *   ville_arrivee:string,
     *   pseudo_chauffeur:string,
     *   email_chauffeur:string,
     *   passagers: array<int, array{pseudo:string, email:string}>
     * }|null
     */
    public function trouverIncidentOuvertParCovoiturage(int $idCovoiturage): ?array
    {
        if ($idCovoiturage <= 0) {
            return null;
        }

        $pdo = $this->connexion->obtenirPdo();

        $stmt = $pdo->prepare("
            SELECT
              c.id_covoiturage,
              COALESCE(c.incident_commentaire, '') AS incident_commentaire,
              COALESCE(c.incident_commentaire, 'Incident') AS libelle,
              c.statut_covoiturage AS statut,
              to_char(c.date_heure_depart, 'YYYY-MM-DD HH24:MI') AS date_depart,
              to_char(c.date_heure_arrivee, 'YYYY-MM-DD HH24:MI') AS date_arrivee,
              c.ville_depart,
              c.ville_arrivee,
              u_ch.pseudo AS pseudo_chauffeur,
              u_ch.email AS email_chauffeur
            FROM covoiturage c
            JOIN utilisateur u_ch ON u_ch.id_utilisateur = c.id_utilisateur
            WHERE c.id_covoiturage = :id
              AND c.statut_covoiturage = 'INCIDENT'
              AND c.incident_resolu = false
            LIMIT 1
        ");
        $stmt->execute(['id' => $idCovoiturage]);

        /** @var array<string, mixed>|false $ligne */
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ligne)) {
            return null;
        }

        $stmt2 = $pdo->prepare("
            SELECT
              u_pa.pseudo,
              u_pa.email
            FROM participation p
            JOIN utilisateur u_pa ON u_pa.id_utilisateur = p.id_utilisateur
            WHERE p.id_covoiturage = :id
              AND p.est_annulee = false
            ORDER BY p.date_heure_confirmation DESC
        ");
        $stmt2->execute(['id' => $idCovoiturage]);

        /** @var array<int, array<string, mixed>> $lignesPassagers */
        $lignesPassagers = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $passagers = [];
        foreach ($lignesPassagers as $lignePassager) {
            $passagers[] = [
                'pseudo' => (string) ($lignePassager['pseudo'] ?? ''),
                'email' => (string) ($lignePassager['email'] ?? ''),
            ];
        }

        return [
            'id_covoiturage' => (int) ($ligne['id_covoiturage'] ?? 0),
            'libelle' => (string) ($ligne['libelle'] ?? ''),
            'incident_commentaire' => (string) ($ligne['incident_commentaire'] ?? ''),
            'statut' => (string) ($ligne['statut'] ?? ''),
            'date_depart' => (string) ($ligne['date_depart'] ?? ''),
            'date_arrivee' => (string) ($ligne['date_arrivee'] ?? ''),
            'ville_depart' => (string) ($ligne['ville_depart'] ?? ''),
            'ville_arrivee' => (string) ($ligne['ville_arrivee'] ?? ''),
            'pseudo_chauffeur' => (string) ($ligne['pseudo_chauffeur'] ?? ''),
            'email_chauffeur' => (string) ($ligne['email_chauffeur'] ?? ''),
            'passagers' => $passagers,
        ];
    }

    /**
     * Met à jour le statut de modération d'un avis.
     *
     * Deux décisions sont possibles :
     * - `VALIDE`
     * - `REFUSE`
     *
     * Si une autre valeur est transmise,
     * la méthode la ramène volontairement à `REFUSE`.
     *
     * La mise à jour n'est autorisée que si l'avis est encore en attente.
     * Cela évite de remodifier un avis déjà traité.
     *
     * @param int $idAvis Identifiant de l'avis.
     * @param string $decision Décision demandée.
     * @param int $idEmploye Identifiant de l'employé modérateur.
     *
     * @return bool True si une ligne a été modifiée, false sinon.
     */
    public function modererAvis(int $idAvis, string $decision, int $idEmploye): bool
    {
        $decision = $decision === 'VALIDE' ? 'VALIDE' : 'REFUSE';

        $pdo = $this->connexion->obtenirPdo();

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

    /**
     * Marque un incident comme traité.
     *
     * La mise à jour n'agit que si :
     * - le covoiturage existe ;
     * - son statut est `INCIDENT` ;
     * - `incident_resolu` vaut encore `false`.
     *
     * @param int $idCovoiturage Identifiant du covoiturage.
     *
     * @return bool True si une ligne a été modifiée, false sinon.
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
