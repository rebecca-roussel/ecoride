<?php
declare(strict_types=1);

namespace App\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * Service métier : créer un covoiturage.
 *
 * Objectif :
 * - vérifier une cohérence métier importante : la voiture doit appartenir au conducteur (id_utilisateur)
 * - insérer le covoiturage dans PostgreSQL (transaction)
 * - forcer statut_covoiturage = 'PLANIFIE' (car NOT NULL sans DEFAULT dans mon schema.sql)
 * - retourner l'id du covoiturage créé (RETURNING id_covoiturage)
 *
 * IMPORTANT :
 * - Ici je fais la logique de création "côté back", sans front.
 * - Le contrôleur reçoit le JSON, puis appelle ce service.
 */
final class CreerCovoiturage
{
    private PDO $pdo;

    /**
     * Symfony va injecter PDO (à condition que tu aies un service PDO configuré).
     */
    public function __construct(PDO $pdo)
    {
        // Je force PDO à lever des exceptions en cas d'erreur SQL (plus simple à gérer)
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo = $pdo;
    }

    /**
     * Crée un covoiturage et renvoie son id.
     *
     * @throws InvalidArgumentException si les règles métiers ne sont pas respectées
     * @throws PDOException si la base a un problème
     */
    public function executer(
        int $id_utilisateur,
        int $id_voiture,
        DateTimeImmutable $date_heure_depart,
        DateTimeImmutable $date_heure_arrivee,
        string $adresse_depart,
        string $adresse_arrivee,
        string $ville_depart,
        string $ville_arrivee,
        int $nb_places_dispo,
        int $prix_credits
    ): int {
        // Par sécurité, je refais une vérification logique (même si le contrôleur l'a déjà faite)
        if ($date_heure_arrivee <= $date_heure_depart) {
            throw new InvalidArgumentException("La date d'arrivée doit être après la date de départ.");
        }

        // Début de transaction : soit tout passe, soit rien n'est enregistré
        $this->pdo->beginTransaction();

        try {
            /*
             * 1) Sécurité métier : vérifier que la voiture appartient bien à l'utilisateur (conducteur)
             * Sinon on pourrait créer un covoiturage avec la voiture de quelqu'un d'autre.
             */
            $sql_verif = "
                SELECT 1
                FROM voiture
                WHERE id_voiture = :id_voiture
                  AND id_utilisateur = :id_utilisateur
            ";
            $stmt_verif = $this->pdo->prepare($sql_verif);
            $stmt_verif->execute([
                ':id_voiture' => $id_voiture,
                ':id_utilisateur' => $id_utilisateur,
            ]);

            $existe = $stmt_verif->fetchColumn();

            if ($existe === false) {
                // Je préfère une erreur claire côté API (400)
                throw new InvalidArgumentException("La voiture sélectionnée n'appartient pas à cet utilisateur.");
            }

            /*
             * 2) Insertion du covoiturage
             * IMPORTANT : statut_covoiturage est NOT NULL sans DEFAULT dans le schema.sql
             * Donc j'impose ici 'PLANIFIE' (valeur autorisée par ck_covoiturage_statut).
             *
             * Je ne fournis pas :
             * - commission_credits => DEFAULT 2
             * - incident_resolu => DEFAULT false
             * - incident_commentaire => NULL (autorisé tant que statut != 'INCIDENT')
             * - latitude/longitude => NULL (optionnel)
             */
            $sql_insert = "
                INSERT INTO covoiturage (
                    date_heure_depart,
                    date_heure_arrivee,
                    adresse_depart,
                    adresse_arrivee,
                    ville_depart,
                    ville_arrivee,
                    nb_places_dispo,
                    prix_credits,
                    statut_covoiturage,
                    id_utilisateur,
                    id_voiture
                )
                VALUES (
                    :date_heure_depart,
                    :date_heure_arrivee,
                    :adresse_depart,
                    :adresse_arrivee,
                    :ville_depart,
                    :ville_arrivee,
                    :nb_places_dispo,
                    :prix_credits,
                    'PLANIFIE',
                    :id_utilisateur,
                    :id_voiture
                )
                RETURNING id_covoiturage
            ";

            $stmt_insert = $this->pdo->prepare($sql_insert);

            // Je formate les DateTime en texte compatible TIMESTAMP
            $stmt_insert->execute([
                ':date_heure_depart'  => $date_heure_depart->format('Y-m-d H:i:s'),
                ':date_heure_arrivee' => $date_heure_arrivee->format('Y-m-d H:i:s'),
                ':adresse_depart'     => $adresse_depart,
                ':adresse_arrivee'    => $adresse_arrivee,
                ':ville_depart'       => $ville_depart,
                ':ville_arrivee'      => $ville_arrivee,
                ':nb_places_dispo'    => $nb_places_dispo,
                ':prix_credits'       => $prix_credits,
                ':id_utilisateur'     => $id_utilisateur,
                ':id_voiture'         => $id_voiture,
            ]);

            // RETURNING => on récupère directement l'id créé
            $id_covoiturage = $stmt_insert->fetchColumn();

            if ($id_covoiturage === false) {
                // Cas très rare, mais je préfère gérer proprement
                throw new PDOException("Impossible de récupérer l'id du covoiturage créé.");
            }

            // Si tout est OK, on valide la transaction
            $this->pdo->commit();

            return (int) $id_covoiturage;
        } catch (\Throwable $e) {
            // En cas d'erreur, on annule la transaction (rollback)
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // On relance l'erreur pour que le contrôleur réponde 400 ou 500 selon le cas
            throw $e;
        }
    }
}
