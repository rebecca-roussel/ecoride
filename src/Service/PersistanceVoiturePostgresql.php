<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Service de persistance PostgreSQL pour les voitures.
 *
 * Cette classe regroupe les requêtes SQL liées aux voitures utilisateur :
 * lecture des voitures actives,
 * lecture d'une voiture précise,
 * ajout d'une voiture,
 * modification d'une voiture
 * et désactivation d'une voiture.
 *
 * Une persistance sert de point d'accès à la base de données.
 * Son rôle est de préparer les requêtes SQL,
 * de les exécuter,
 * puis de renvoyer des résultats exploitables
 * par le reste de l'application.
 *
 */
final class PersistanceVoiturePostgresql
{
    /**
     * Initialise le service avec la connexion PostgreSQL.
     *
     * @param ConnexionPostgresql $connexionPostgresql
     *        Service qui fournit l'objet PDO.
     */
    public function __construct(private ConnexionPostgresql $connexionPostgresql)
    {
    }

    /**
     * Normalise une immatriculation.
     *
     * La valeur est nettoyée puis convertie en majuscules
     * pour garder un format cohérent avant l'enregistrement en base.
     *
     * @param string $immatriculation Immatriculation brute.
     *
     * @return string Immatriculation normalisée.
     */
    private function normaliserImmatriculation(string $immatriculation): string
    {
        return strtoupper(trim($immatriculation));
    }

    /**
     * Liste les voitures actives d'un utilisateur.
     *
     * Une voiture active est une voiture dont `est_active = true`.
     * Cette lecture sert notamment à l'affichage de l'espace véhicule
     * et à la publication d'un covoiturage.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur propriétaire.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listerVoituresActives(int $idUtilisateur): array
    {
        if ($idUtilisateur <= 0) {
            return [];
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
            SELECT
              v.id_voiture,
              v.immatriculation,
              v.date_1ere_mise_en_circulation,
              v.marque,
              v.couleur,
              v.energie,
              v.nb_places
            FROM voiture v
            WHERE v.id_utilisateur = :id_utilisateur
              AND v.est_active = true
            ORDER BY v.id_voiture DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_utilisateur' => $idUtilisateur,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Alias de compatibilité pour la lecture des voitures actives.
     *
     * Le projet utilise encore le mot "vehicule" dans certains endroits.
     * Cette méthode garde donc une signature compatible
     * tout en appelant la vraie méthode de lecture.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listerVehiculesActifsParUtilisateur(int $idUtilisateur): array
    {
        return $this->listerVoituresActives($idUtilisateur);
    }

    /**
     * Recherche une voiture précise appartenant à un utilisateur donné.
     *
     * La double condition sur l'identifiant de la voiture
     * et l'identifiant de l'utilisateur évite
     * qu'un compte accède à la voiture d'un autre.
     *
     * @param int $idVoiture Identifiant de la voiture.
     * @param int $idUtilisateur Identifiant du propriétaire attendu.
     *
     * @return array<string, mixed>|null
     */
    public function trouverVehiculeParIdEtUtilisateur(int $idVoiture, int $idUtilisateur): ?array
    {
        if ($idVoiture <= 0 || $idUtilisateur <= 0) {
            return null;
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
            SELECT
              id_voiture,
              immatriculation,
              date_1ere_mise_en_circulation,
              marque,
              couleur,
              energie,
              nb_places,
              est_active
            FROM voiture
            WHERE id_voiture = :id_voiture
              AND id_utilisateur = :id_utilisateur
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_voiture' => $idVoiture,
            'id_utilisateur' => $idUtilisateur,
        ]);

        $vehicule = $stmt->fetch(PDO::FETCH_ASSOC);

        return $vehicule !== false ? $vehicule : null;
    }

    /**
     * Ajoute une voiture pour un utilisateur donné.
     *
     * L'immatriculation est normalisée avant l'insertion.
     * En cas d'erreur PostgreSQL, la méthode renvoie
     * un message métier compréhensible par le contrôleur.
     *
     * @param int $idUtilisateur Identifiant du propriétaire.
     * @param string $immatriculation Immatriculation saisie.
     * @param string $dateYmd Date de première mise en circulation au format `Y-m-d`.
     * @param string $marque Marque de la voiture.
     * @param string $couleur Couleur de la voiture.
     * @param string $energie Énergie de la voiture.
     * @param int $nbPlaces Nombre de places.
     *
     * @return int Identifiant PostgreSQL de la voiture créée.
     */
    public function ajouterVehicule(
        int $idUtilisateur,
        string $immatriculation,
        string $dateYmd,
        string $marque,
        string $couleur,
        string $energie,
        int $nbPlaces
    ): int {
        if ($idUtilisateur <= 0) {
            throw new RuntimeException('Ajout impossible : utilisateur invalide.');
        }

        $immatriculation = $this->normaliserImmatriculation($immatriculation);

        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
            INSERT INTO voiture (
              immatriculation,
              date_1ere_mise_en_circulation,
              marque,
              couleur,
              energie,
              nb_places,
              id_utilisateur
            )
            VALUES (
              :immatriculation,
              :date_1ere_mise_en_circulation,
              :marque,
              :couleur,
              :energie,
              :nb_places,
              :id_utilisateur
            )
            RETURNING id_voiture
        ";

        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute([
                'immatriculation' => $immatriculation,
                'date_1ere_mise_en_circulation' => $dateYmd,
                'marque' => trim($marque),
                'couleur' => trim($couleur),
                'energie' => trim($energie),
                'nb_places' => $nbPlaces,
                'id_utilisateur' => $idUtilisateur,
            ]);

            $idVoiture = $stmt->fetchColumn();
            if ($idVoiture === false) {
                throw new RuntimeException('Ajout impossible : aucun identifiant de voiture retourné.');
            }

            return (int) $idVoiture;
        } catch (PDOException) {
            throw new RuntimeException('Ajout impossible : immatriculation déjà utilisée ou données invalides.');
        }
    }

    /**
     * Modifie une voiture existante appartenant à l'utilisateur.
     *
     * La méthode vérifie d'abord qu'aucune autre voiture
     * n'utilise déjà la même immatriculation.
     * Elle applique ensuite la mise à jour
     * uniquement si la voiture appartient bien à l'utilisateur
     * et qu'elle est encore active.
     *
     * @param int $idVoiture Identifiant de la voiture.
     * @param int $idUtilisateur Identifiant du propriétaire.
     * @param string $immatriculation Nouvelle immatriculation.
     * @param string $dateYmd Nouvelle date de première mise en circulation.
     * @param string $marque Nouvelle marque.
     * @param string $couleur Nouvelle couleur.
     * @param string $energie Nouvelle énergie.
     * @param int $nbPlaces Nouveau nombre de places.
     *
     * @return void
     */
    public function modifierVehicule(
        int $idVoiture,
        int $idUtilisateur,
        string $immatriculation,
        string $dateYmd,
        string $marque,
        string $couleur,
        string $energie,
        int $nbPlaces
    ): void {
        if ($idVoiture <= 0 || $idUtilisateur <= 0) {
            throw new RuntimeException('Modification impossible : paramètres invalides.');
        }

        $immatriculation = $this->normaliserImmatriculation($immatriculation);

        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sqlVerif = "
            SELECT 1
            FROM voiture
            WHERE immatriculation = :immatriculation
              AND id_voiture <> :id_voiture
            LIMIT 1
        ";

        $stmtVerif = $pdo->prepare($sqlVerif);
        $stmtVerif->execute([
            'immatriculation' => $immatriculation,
            'id_voiture' => $idVoiture,
        ]);

        if ($stmtVerif->fetchColumn() !== false) {
            throw new RuntimeException('Immatriculation déjà utilisée.');
        }

        $sql = "
            UPDATE voiture
            SET immatriculation = :immatriculation,
                date_1ere_mise_en_circulation = :date_1ere_mise_en_circulation,
                marque = :marque,
                couleur = :couleur,
                energie = :energie,
                nb_places = :nb_places
            WHERE id_voiture = :id_voiture
              AND id_utilisateur = :id_utilisateur
              AND est_active = true
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'immatriculation' => $immatriculation,
            'date_1ere_mise_en_circulation' => $dateYmd,
            'marque' => trim($marque),
            'couleur' => trim($couleur),
            'energie' => trim($energie),
            'nb_places' => $nbPlaces,
            'id_voiture' => $idVoiture,
            'id_utilisateur' => $idUtilisateur,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Modification impossible : véhicule introuvable ou non autorisé.');
        }
    }

    /**
     * Désactive une voiture au lieu de la supprimer physiquement.
     *
     * Ici, on fait une suppression logique :
     * la voiture reste enregistrée dans PostgreSQL,
     * mais elle passe en inactive et reçoit une date de désactivation.
     *
     * Ce choix est important pour garder un historique cohérent.
     * En effet, une voiture peut déjà être liée à un ou plusieurs covoiturages
     * par la clé étrangère `id_voiture`.
     *
     * Une clé étrangère est un lien entre deux tables.
     * Présentement, elle relie un covoiturage à la voiture utilisée pour ce trajet.
     *
     * Si on supprimait réellement la voiture,
     * on risquerait soit de casser ce lien,
     * soit de perdre une partie de l'historique utile autour des covoiturages déjà créés.
     *
     * La désactivation permet donc deux choses :
     * - empêcher toute réutilisation future de cette voiture ;
     * - conserver les données déjà liées aux anciens covoiturages.
     *
     * @param int $idVoiture Identifiant de la voiture.
     * @param int $idUtilisateur Identifiant du propriétaire.
     *
     * @return void
     */
    public function desactiverVehicule(int $idVoiture, int $idUtilisateur): void
    {
        if ($idVoiture <= 0 || $idUtilisateur <= 0) {
            throw new RuntimeException('Suppression impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
        * On ne supprime pas la ligne de la table `voiture`.
        * On marque simplement la voiture comme inactive
        * et on conserve la date de désactivation.
        */
        $sql = "
            UPDATE voiture
            SET est_active = false,
                date_desactivation = NOW()
            WHERE id_voiture = :id_voiture
            AND id_utilisateur = :id_utilisateur
            AND est_active = true
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_voiture' => $idVoiture,
            'id_utilisateur' => $idUtilisateur,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Suppression impossible : véhicule introuvable ou non autorisé.');
        }
    }
}