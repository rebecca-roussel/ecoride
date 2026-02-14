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

      1) Lister les véhicules actifs d’un utilisateur
         - requête préparée
         - filtrer sur est_active

      2) Trouver un véhicule (sécurité : id + utilisateur)
         - utile pour l’édition

      3) Ajouter un véhicule
         - insertion + id retourné
         - erreurs PDO transformées en message métier

      4) Modifier un véhicule
         - vérifier unicité immatriculation (sauf lui-même)
         - mise à jour uniquement si appartient à l’utilisateur

      5) Désactiver un véhicule (suppression logique)
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listerVehiculesActifsParUtilisateur(int $idUtilisateur): array
    {
        // Sécurité : id invalide -> liste vide (pas besoin de polluer le journal).
        if ($idUtilisateur <= 0) {
            return [];
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
                nb_places
            FROM voiture
            WHERE id_utilisateur = :id_utilisateur
              AND est_active = true
            ORDER BY id_voiture DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_utilisateur' => $idUtilisateur,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
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
                nb_places
            FROM voiture
            WHERE id_voiture = :id_voiture
              AND id_utilisateur = :id_utilisateur
              AND est_active = true
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_voiture' => $idVoiture,
            ':id_utilisateur' => $idUtilisateur,
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
        if ($idUtilisateur <= 0) {
            throw new RuntimeException("Ajout impossible : utilisateur invalide.");
        }

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
                ':immatriculation' => $immatriculation,
                ':date_1ere_mise_en_circulation' => $dateYmd,
                ':marque' => $marque,
                ':couleur' => $couleur,
                ':energie' => $energie,
                ':nb_places' => $nbPlaces,
                ':id_utilisateur' => $idUtilisateur,
            ]);

            $idVoiture = (int) $stmt->fetchColumn();

            // Note : ici on ne journalise pas "vehicule_ajoute" pour éviter le doublon,
            // car le contrôleur le fait déjà après succès.
            return $idVoiture;
        } catch (PDOException $e) {
            // Cas fréquent : immatriculation unique, ou contrainte BDD.
            $this->journalEvenements->enregistrerErreur(
                'vehicule_ajout_erreur',
                'utilisateur',
                $idUtilisateur,
                $e,
                [
                    'immatriculation' => $immatriculation,
                ]
            );

            throw new RuntimeException("Ajout impossible : immatriculation déjà utilisée ou données invalides.");
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
        if ($idVoiture <= 0 || $idUtilisateur <= 0) {
            throw new RuntimeException('Modification impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        // Sécurité : je refuse les collisions d’immatriculation (sauf lui-même).
        $sqlVerif = "
            SELECT 1
            FROM voiture
            WHERE immatriculation = :immatriculation
              AND id_voiture <> :id_voiture
            LIMIT 1
        ";
        $stmtVerif = $pdo->prepare($sqlVerif);
        $stmtVerif->execute([
            ':immatriculation' => $immatriculation,
            ':id_voiture' => $idVoiture,
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
            ':immatriculation' => $immatriculation,
            ':date_1ere_mise_en_circulation' => $dateYmd,
            ':marque' => $marque,
            ':couleur' => $couleur,
            ':energie' => $energie,
            ':nb_places' => $nbPlaces,
            ':id_voiture' => $idVoiture,
            ':id_utilisateur' => $idUtilisateur,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Modification impossible : véhicule introuvable ou non autorisé.');
        }
    }

    public function desactiverVehicule(int $idVoiture, int $idUtilisateur): void
    {
        /*
          Sécurité :
          - ids invalides : je journalise et je bloque
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
            ':id_voiture' => $idVoiture,
            ':id_utilisateur' => $idUtilisateur,
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

        // Note : idem, pas de doublon ici si le contrôleur journalise déjà la suppression.
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
          Parsing strict :
          - createFromFormat peut renvoyer un objet même si la date n'est pas logique
          - je vérifie aussi les erreurs de parsing
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
