<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

final class PersistanceVoiturePostgresql
{
    /*
      PLAN (PersistanceVoiturePostgresql) :

      1) Lister les voitures actives d’un utilisateur
         - une seule méthode “source de vérité”
         - sert à “Gérer mes véhicules” et à “Publier un covoiturage”

      2) Trouver une voiture (sécurité : id + utilisateur)
         - pour afficher et modifier un véhicule

      3) Ajouter une voiture
         - insertion + id retourné
         - erreurs BDD converties en message simple

      4) Modifier une voiture
         - vérifier unicité immatriculation (sauf lui-même)
         - mise à jour seulement si appartient à l’utilisateur

      5) Désactiver une voiture (suppression logique)
         - est_active passe à false + date_desactivation
         - vérifier appartenance + état actif

      6) Aide d’affichage
         - calculer l’ancienneté en années
    */

    public function __construct(
        private ConnexionPostgresql $connexionPostgresql,
        private JournalEvenements $journalEvenements
    ) {
    }

    /* Normaliser une immatriculation */
    private function normaliserImmatriculation(string $immatriculation): string
    {
        $immatriculation = strtoupper(trim($immatriculation));

        return $immatriculation;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listerVoituresActives(int $idUtilisateur): array
    {
        /*
          - récupérer uniquement les voitures actives de l’utilisateur
          - utile pour publier un covoiturage
        */

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
     * @return array<int, array<string, mixed>>
     */
    public function listerVehiculesActifsParUtilisateur(int $idUtilisateur): array
    {
        /* Compatibilité */

        return $this->listerVoituresActives($idUtilisateur);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouverVehiculeParIdEtUtilisateur(int $idVoiture, int $idUtilisateur): ?array
    {
        /*
          - récupérer une voiture en étant sûre qu’elle appartient bien à l’utilisateur
          - éviter qu’un utilisateur accède à la voiture d’un autre
        */

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

    public function ajouterVehicule(
        int $idUtilisateur,
        string $immatriculation,
        string $dateYmd,
        string $marque,
        string $couleur,
        string $energie,
        int $nbPlaces
    ): int {

        /*
          - créer un voiture lié à l’utilisateur
          - laisser la BDD validée le format 
        */

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

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            /*
              Journal evenement:
              - on garde la trace technique côté MongoDB
              - mais on renvoie un message simple à l’utilisateur
            */
            $this->journalEvenements->enregistrerErreur(
                'vehicule_ajout_erreur',
                'utilisateur',
                $idUtilisateur,
                $e,
                [
                    'immatriculation' => $immatriculation,
                ]
            );

            throw new RuntimeException('Ajout impossible : immatriculation déjà utilisée ou données invalides.');
        }
    }

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
        /*
          - mise à jour d’une voiture
          - uniquement si la voiture appartient à l’utilisateur
        */

        if ($idVoiture <= 0 || $idUtilisateur <= 0) {
            throw new RuntimeException('Modification impossible : paramètres invalides.');
        }

        $immatriculation = $this->normaliserImmatriculation($immatriculation);

        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
          Sécurité !
          - l’immatriculation est unique
          - je refuse une collision (sauf le véhicule actuel)
        */
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

    public function desactiverVehicule(int $idVoiture, int $idUtilisateur): void
    {
        /*
          - suppression logique donc désactivation
          - on garde l’historique
        */

        if ($idVoiture <= 0 || $idUtilisateur <= 0) {
            $this->journalEvenements->enregistrer(
                'vehicule_suppression_refusee',
                'utilisateur',
                max(0, $idUtilisateur),
                [
                    'raison' => 'parametres_invalides',
                    'id_voiture' => $idVoiture,
                ]
            );

            throw new RuntimeException('Suppression impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

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
            $this->journalEvenements->enregistrer(
                'vehicule_suppression_refusee',
                'voiture',
                $idVoiture,
                [
                    'raison' => 'introuvable_ou_non_autorise',
                    'id_utilisateur' => $idUtilisateur,
                ]
            );

            throw new RuntimeException('Suppression impossible : véhicule introuvable ou non autorisé.');
        }

        $this->journalEvenements->enregistrer(
            'vehicule_desactive',
            'voiture',
            $idVoiture,
            [
                'id_utilisateur' => $idUtilisateur,
            ]
        );
    }

    public function calculerAncienneteEnAnnees(string $dateYmd, ?int $idUtilisateur = null): int
    {
        /*
          - calcul simple pour l’affichage
          - si date invalide on renvoie 0 et on peut tracer
        */

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd);
        $erreurs = DateTimeImmutable::getLastErrors();

        $parsingKo = !$date instanceof DateTimeImmutable
            || ($erreurs !== false && ($erreurs['warning_count'] > 0 || $erreurs['error_count'] > 0));

        if ($parsingKo) {
            if ($idUtilisateur !== null) {
                $this->journalEvenements->enregistrer(
                    'vehicule_date_invalide',
                    'utilisateur',
                    $idUtilisateur,
                    [
                        'date_recue' => $dateYmd,
                        'raison' => 'format_attendu_yyyy_mm_dd',
                    ]
                );
            }

            return 0;
        }

        $aujourdhui = new DateTimeImmutable('today');
        $diff = $date->diff($aujourdhui);

        return max(0, (int) $diff->y);
    }
}

