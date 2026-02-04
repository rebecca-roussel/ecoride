<?php
declare(strict_types=1);

namespace App\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;

/*
 * Cette classe fait une chose précise :
 * -> créer un covoiturage en base PostgreSQL.
 *
 * Idée simple :
 * - le contrôleur reçoit la requête HTTP (JSON)
 * - ici, je fais le "travail de fond" : vérifications + insertion SQL
 *
 * Pourquoi je sépare ?
 * - ça évite de mettre du SQL dans le contrôleur
 * - c’est plus clair et réutilisable
 */
final class CreerCovoiturage
{
    /*
     * PDO = la connexion à PostgreSQL.
     * Je la garde ici pour faire les requêtes SQL.
     */
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        /*
         * Je veux que PDO me prévienne clairement si une requête SQL échoue.
         * Sinon, PDO peut juste renvoyer "false" et c’est plus dur à comprendre.
         */
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo = $pdo;
    }

    /*
     * Cette méthode crée le covoiturage et renvoie l'id créé.
     *
     * Elle reçoit toutes les infos utiles (chauffeur, voiture, dates, adresses, prix, etc.)
     * puis :
     * 1) vérifie une règle importante
     * 2) insère dans la table covoiturage
     * 3) renvoie l'id du covoiturage créé
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
        /*
         * Vérification simple :
         * l’arrivée doit être après le départ.
         * Sinon ça n’a aucun sens et PostgreSQL refusera aussi (contrainte CHECK).
         */
        if ($date_heure_arrivee <= $date_heure_depart) {
            throw new InvalidArgumentException("La date d'arrivée doit être après la date de départ.");
        }

        /*
         * Transaction = mode "tout ou rien".
         * Si un seul morceau échoue, je ne veux pas laisser une base dans un état bizarre.
         */
        $this->pdo->beginTransaction();

        try {
            /*
             * 1) Sécurité : vérifier que la voiture appartient bien à l'utilisateur.
             * Sinon, quelqu’un pourrait créer un covoiturage avec la voiture d’un autre.
             */
            $stmt_verif = $this->pdo->prepare("
                SELECT 1
                FROM voiture
                WHERE id_voiture = :id_voiture
                  AND id_utilisateur = :id_utilisateur
            ");

            /*
             * J’envoie les valeurs à PostgreSQL avec des paramètres.
             * Avantage : évite les problèmes de sécurité et les erreurs de format.
             */
            $stmt_verif->execute([
                ':id_voiture' => $id_voiture,
                ':id_utilisateur' => $id_utilisateur,
            ]);

            /*
             * fetchColumn() me renvoie une valeur si une ligne existe,
             * ou false si aucune ligne n’a été trouvée.
             */
            if ($stmt_verif->fetchColumn() === false) {
                throw new InvalidArgumentException("La voiture sélectionnée n'appartient pas à cet utilisateur.");
            }

            /*
             * 2) Insertion du covoiturage.
             *
             * Important :
             * - statut_covoiturage est obligatoire
             * - je force 'PLANIFIE' car c’est une valeur autorisée dans mon schéma SQL
             */
            $stmt_insert = $this->pdo->prepare("
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
            ");

            /*
             * PostgreSQL attend un TIMESTAMP.
             * Donc je transforme DateTimeImmutable en texte au bon format.
             */
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

            /*
             * Grâce au "RETURNING id_covoiturage",
             * je récupère directement l'id créé.
             */
            $id = $stmt_insert->fetchColumn();

            if ($id === false) {
                // Cas rare, mais je préfère gérer proprement
                throw new PDOException("Impossible de récupérer l'id du covoiturage créé.");
            }

            /*
             * Tout s’est bien passé :
             * je valide la transaction.
             */
            $this->pdo->commit();

            return (int) $id;
        } catch (\Throwable $e) {
            /*
             * Si une erreur arrive :
             * j’annule tout ce qui a été commencé dans la transaction.
             */
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Je relance l'erreur, ce sera géré plus haut (contrôleur / Symfony)
            throw $e;
        }
    }
}
