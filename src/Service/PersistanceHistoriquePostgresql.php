<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Service de persistance PostgreSQL pour l'historique utilisateur.
 *
 * Cette classe regroupe les accès SQL utilisés par l'écran "Mon historique".
 * Elle couvre deux grands besoins :
 *
 * - relire les trajets affichés dans les deux onglets ;
 * - exécuter les actions métier liées à ces trajets.
 *
 * Côté lecture, on alimente :
 * - l'onglet "Mes covoiturages publiés" pour le chauffeur ;
 * - l'onglet "Mes participations" pour le passager.
 *
 * Côté écriture, on gère :
 * - l'annulation d'une participation ;
 * - l'annulation d'un covoiturage ;
 * - le démarrage d'un covoiturage ;
 * - la terminaison d'un covoiturage ;
 * - la déclaration d'un incident ;
 * - l'enregistrement d'une satisfaction avec création d'un avis.
 *
 * Le rôle de cette classe est volontairement centré sur PostgreSQL :
 * elle prépare les requêtes SQL, ouvre les transactions,
 * vérifie les retours SQL importants,
 * puis remonte aux contrôleurs soit un résultat,
 * soit une exception métier compréhensible.
 *
 * Les contrôleurs gardent alors la partie HTTP :
 * lecture de la requête, contrôle CSRF, messages flash, redirections et journalisation MongoDB.
 */
final class PersistanceHistoriquePostgresql
{
    public function __construct(private ConnexionPostgresql $connexionPostgresql)
    {
    }

    /**
     * Liste les covoiturages publiés par l'utilisateur connecté.
     *
     * Cette lecture alimente l'onglet "Mes covoiturages publiés".
     * On récupère uniquement les covoiturages dont l'utilisateur est le chauffeur.
     *
     * Les colonnes sélectionnées correspondent aux informations affichées dans la carte :
     * - trajet ;
     * - date de départ ;
     * - places disponibles ;
     * - prix ;
     * - statut du covoiturage ;
     * - commentaire d'incident si un incident existe.
     *
     * Le tri est fait ici par date de départ croissante.
     * Cela permet d'afficher d'abord les trajets les plus proches,
     * ce qui rend l'onglet plus cohérent à lire.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur connecté.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listerCovoituragesPublies(int $idUtilisateur): array
    {
        /*
         * Une lecture SQL n'a de sens ici
         * que si on connaît clairement l'utilisateur concerné.
         */
        if ($idUtilisateur <= 0) {
            throw new RuntimeException('ID utilisateur invalide.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
         * On lit uniquement les trajets publiés par cet utilisateur.
         *
         * Point important sur le tri :
         * on utilise ASC pour faire ressortir d'abord les covoiturages
         * dont la date est la plus proche.
         */
        $sql = "
            SELECT
              c.id_covoiturage,
              c.ville_depart,
              c.ville_arrivee,
              c.date_heure_depart,
              c.nb_places_dispo,
              c.prix_credits,
              c.statut_covoiturage,
              c.incident_commentaire
            FROM covoiturage c
            WHERE c.id_utilisateur = :id_utilisateur
            ORDER BY c.date_heure_depart ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_utilisateur' => $idUtilisateur,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Liste les participations de l'utilisateur connecté.
     *
     * Cette lecture alimente l'onglet "Mes participations".
     * On part de la table participation,
     * puis on joint le covoiturage lié pour afficher le contexte du trajet.
     *
     * Les colonnes relues servent à afficher :
     * - l'état de la participation ;
     * - le nombre de crédits utilisés ;
     * - le statut de validation ;
     * - le trajet ;
     * - la date de départ ;
     * - le prix ;
     * - le statut du covoiturage.
     *
     * Le tri est fait en ordre croissant sur la date de départ
     * pour afficher d'abord les trajets les plus proches.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur connecté.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listerParticipations(int $idUtilisateur): array
    {
        /*
         * Même garde que pour les covoiturages publiés :
         * sans identifiant utilisateur valide,
         * la requête n'a pas de cible cohérente.
         */
        if ($idUtilisateur <= 0) {
            throw new RuntimeException('ID utilisateur invalide.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
         * On récupère d'abord la participation,
         * puis le covoiturage correspondant.
         *
         * Le tri en ASC rend l'onglet plus lisible :
         * le passager voit en premier les trajets à venir les plus proches.
         */
        $sql = "
            SELECT
              p.id_participation,
              p.est_annulee,
              p.credits_utilises,
              p.statut_validation,
              p.commentaire_validation,

              c.id_covoiturage,
              c.ville_depart,
              c.ville_arrivee,
              c.date_heure_depart,
              c.prix_credits,
              c.statut_covoiturage
            FROM participation p
            JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
            WHERE p.id_utilisateur = :id_utilisateur
            ORDER BY c.date_heure_depart ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_utilisateur' => $idUtilisateur,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Annule une participation.
     *
     * Cette méthode travaille dans une transaction
     * pour garder une opération cohérente du début à la fin.
     *
     * Avant de modifier la participation,
     * on vérifie plusieurs points :
     * - la participation existe ;
     * - elle appartient bien à l'utilisateur connecté ;
     * - elle n'est pas déjà annulée ;
     * - le trajet n'est pas déjà terminé ou annulé.
     *
     * Si toutes les conditions sont remplies,
     * la participation passe en annulée
     * et son statut de validation revient à NON_DEMANDEE.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur connecté.
     * @param int $idParticipation Identifiant de la participation à annuler.
     *
     * @return void
     */
    public function annulerParticipation(int $idUtilisateur, int $idParticipation): void
    {
        if ($idUtilisateur <= 0 || $idParticipation <= 0) {
            throw new RuntimeException('Annulation impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            /*
             * On relit la participation avec le statut du covoiturage.
             * Cela permet de contrôler à la fois l'appartenance
             * et l'état du trajet au moment où l'utilisateur clique.
             */
            $stmt = $pdo->prepare("
                SELECT p.est_annulee, c.statut_covoiturage
                FROM participation p
                JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
                WHERE p.id_participation = :id_participation
                AND p.id_utilisateur = :id_utilisateur
                LIMIT 1
            ");

            $stmt->execute([
                'id_participation' => $idParticipation,
                'id_utilisateur' => $idUtilisateur,
            ]);

            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ligne) {
                throw new RuntimeException('Annulation refusée : participation introuvable.');
            }

            if ((bool) $ligne['est_annulee']) {
                throw new RuntimeException('Annulation refusée : participation déjà annulée.');
            }

            $statutCovoiturage = $ligne['statut_covoiturage'];

            /*
             * Une participation n'a plus vocation à être annulée
             * quand le trajet est terminé ou déjà annulé.
             */
            if (in_array($statutCovoiturage, ['TERMINE', 'ANNULE'], true)) {
                throw new RuntimeException('Annulation refusée : ce trajet n’est plus annulable.');
            }

            /*
             * On marque la participation comme annulée.
             * Le statut de validation repasse à NON_DEMANDEE
             * parce que cette participation sort du parcours normal.
             */
            $stmt = $pdo->prepare("
                UPDATE participation
                SET est_annulee = true, statut_validation = 'NON_DEMANDEE', commentaire_validation = NULL
                WHERE id_participation = :id_participation
            ");
            $stmt->execute([
                'id_participation' => $idParticipation,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Annulation refusée : mise à jour impossible.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Annule un covoiturage publié par le chauffeur connecté.
     *
     * La méthode vérifie d'abord que :
     * - le covoiturage existe ;
     * - il appartient bien à l'utilisateur connecté ;
     * - il n'est pas déjà annulé ;
     * - il n'est pas déjà terminé ;
     * - il n'est pas déjà en cours.
     *
     * Si l'annulation est autorisée :
     * - le covoiturage passe au statut ANNULE ;
     * - toutes les participations actives liées à ce trajet sont aussi annulées.
     *
     * La transaction permet de ne pas laisser
     * un covoiturage annulé avec des participations encore actives.
     *
     * @param int $idUtilisateur Identifiant du chauffeur connecté.
     * @param int $idCovoiturage Identifiant du covoiturage à annuler.
     *
     * @return void
     */
    public function annulerCovoiturage(int $idUtilisateur, int $idCovoiturage): void
    {
        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            throw new RuntimeException('Annulation impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            /*
             * On relit le covoiturage pour vérifier
             * qu'il appartient bien au chauffeur connecté
             * et qu'il peut encore être annulé.
             */
            $stmt = $pdo->prepare("
                SELECT statut_covoiturage
                FROM covoiturage
                WHERE id_covoiturage = :id_covoiturage
                AND id_utilisateur = :id_utilisateur
                LIMIT 1
            ");

            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
                'id_utilisateur' => $idUtilisateur,
            ]);

            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ligne) {
                throw new RuntimeException('Annulation refusée : covoiturage introuvable.');
            }

            $statutCovoiturage = $ligne['statut_covoiturage'];

            if ($statutCovoiturage === 'ANNULE') {
                throw new RuntimeException('Annulation refusée : covoiturage déjà annulé.');
            }

            if ($statutCovoiturage === 'TERMINE') {
                throw new RuntimeException('Annulation refusée : covoiturage déjà terminé.');
            }

            if ($statutCovoiturage === 'EN_COURS') {
                throw new RuntimeException('Annulation refusée : ce covoiturage est déjà en cours.');
            }

            /*
             * On annule d'abord le covoiturage lui-même.
             */
            $stmt = $pdo->prepare("
                UPDATE covoiturage
                SET statut_covoiturage = 'ANNULE'
                WHERE id_covoiturage = :id_covoiturage
            ");
            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Annulation refusée : mise à jour du covoiturage impossible.');
            }

            /*
             * On annule ensuite toutes les participations encore actives sur ce trajet.
             * Ici, on ne vérifie pas rowCount(),
             * car il est normal qu'un covoiturage puisse n'avoir aucune participation.
             */
            $stmt = $pdo->prepare("
                UPDATE participation
                SET est_annulee = true, statut_validation = 'NON_DEMANDEE', commentaire_validation = NULL
                WHERE id_covoiturage = :id_covoiturage AND est_annulee = false
            ");
            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Déclare un incident sur un covoiturage.
     *
     * Cette action est réservée au chauffeur du trajet.
     * Le commentaire est obligatoire.
     * Le covoiturage doit être en cours ou terminé.
     * Un trajet déjà passé en INCIDENT ne peut pas recevoir un second signalement.
     *
     * La transaction et le verrouillage SQL évitent
     * qu'un autre traitement modifie le statut entre la vérification
     * et la mise à jour.
     *
     * @param int $idUtilisateur Identifiant du chauffeur connecté.
     * @param int $idCovoiturage Identifiant du covoiturage concerné.
     * @param string $commentaire Commentaire décrivant l'incident.
     *
     * @return void
     */
    public function declarerIncident(int $idUtilisateur, int $idCovoiturage, string $commentaire): void
    {
        /*
         * Le commentaire est nettoyé avant validation.
         * Cela évite d'accepter une chaîne remplie uniquement d'espaces.
         */
        $commentaire = trim($commentaire);

        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            throw new RuntimeException('Incident impossible : paramètres invalides.');
        }

        if ($commentaire === '') {
            throw new RuntimeException('Incident refusé : commentaire obligatoire.');
        }

        if (mb_strlen($commentaire) > 1000) {
            throw new RuntimeException('Incident refusé : commentaire trop long (1000 caractères max).');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            /*
             * On verrouille la ligne covoiturage ciblée.
             * Le FOR UPDATE empêche qu'un autre traitement
             * change ce trajet pendant qu'on applique les règles métier.
             */
            $stmt = $pdo->prepare("
                SELECT statut_covoiturage
                FROM covoiturage
                WHERE id_covoiturage = :id_covoiturage
                  AND id_utilisateur = :id_utilisateur
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
                'id_utilisateur' => $idUtilisateur,
            ]);

            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ligne === false) {
                throw new RuntimeException('Incident refusé : covoiturage introuvable.');
            }

            $statutCovoiturage = (string) $ligne['statut_covoiturage'];

            if ($statutCovoiturage === 'INCIDENT') {
                throw new RuntimeException('Incident refusé : un incident est déjà déclaré.');
            }

            if (!in_array($statutCovoiturage, ['EN_COURS', 'TERMINE'], true)) {
                throw new RuntimeException(
                    'Incident refusé : ce covoiturage n’est pas éligible (en cours ou terminé uniquement).'
                );
            }

            /*
             * Si tout est cohérent, le trajet passe en INCIDENT
             * et le commentaire est enregistré dans la table covoiturage.
             */
            $stmt = $pdo->prepare("
                UPDATE covoiturage
                SET statut_covoiturage = 'INCIDENT',
                    incident_commentaire = :incident_commentaire,
                    incident_resolu = false
                WHERE id_covoiturage = :id_covoiturage
                  AND id_utilisateur = :id_utilisateur
                  AND statut_covoiturage IN ('EN_COURS', 'TERMINE')
            ");
            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
                'id_utilisateur' => $idUtilisateur,
                'incident_commentaire' => $commentaire,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Incident refusé : mise à jour impossible (statut modifié entre-temps).');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Démarre un covoiturage.
     *
     * Cette action est réservée au chauffeur du trajet.
     * Le covoiturage doit encore être en statut PLANIFIE.
     *
     * Si la règle est respectée,
     * le trajet passe simplement à EN_COURS.
     *
     * @param int $idUtilisateur Identifiant du chauffeur connecté.
     * @param int $idCovoiturage Identifiant du covoiturage à démarrer.
     *
     * @return void
     */
    public function demarrerCovoiturage(int $idUtilisateur, int $idCovoiturage): void
    {
        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            throw new RuntimeException('Démarrage impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            /*
             * On relit le covoiturage pour vérifier
             * que le chauffeur agit bien sur son propre trajet
             * et que le statut actuel autorise le démarrage.
             */
            $stmt = $pdo->prepare("
                SELECT statut_covoiturage
                FROM covoiturage
                WHERE id_covoiturage = :id_covoiturage
                  AND id_utilisateur = :id_utilisateur
                LIMIT 1
            ");
            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
                'id_utilisateur' => $idUtilisateur,
            ]);

            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ligne === false) {
                throw new RuntimeException('Covoiturage introuvable.');
            }

            $statutCovoiturage = (string) $ligne['statut_covoiturage'];

            if ($statutCovoiturage !== 'PLANIFIE') {
                throw new RuntimeException('Démarrage refusé : ce covoiturage n’est pas planifié.');
            }

            /*
             * Le trajet passe ensuite en EN_COURS.
             */
            $stmt = $pdo->prepare("
                UPDATE covoiturage
                SET statut_covoiturage = 'EN_COURS'
                WHERE id_covoiturage = :id_covoiturage
            ");
            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Démarrage refusé : mise à jour du statut impossible.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Termine un covoiturage.
     *
     * Cette action est réservée au chauffeur.
     * Le trajet doit être actuellement en cours.
     *
     * Si tout est cohérent :
     * - le covoiturage passe en TERMINE ;
     * - les participations actives passent en EN_ATTENTE,
     *   ce qui ouvre ensuite le parcours de validation côté passager.
     *
     * @param int $idUtilisateur Identifiant du chauffeur connecté.
     * @param int $idCovoiturage Identifiant du covoiturage à terminer.
     *
     * @return void
     */
    public function terminerCovoiturage(int $idUtilisateur, int $idCovoiturage): void
    {
        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            throw new RuntimeException('Terminaison impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            /*
             * On verrouille la ligne du covoiturage pour stabiliser l'état lu.
             * Cela évite qu'un autre traitement change le statut
             * pendant la vérification.
             */
            $stmt = $pdo->prepare("
                SELECT statut_covoiturage
                FROM covoiturage
                WHERE id_covoiturage = :id_covoiturage
                  AND id_utilisateur = :id_utilisateur
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
                'id_utilisateur' => $idUtilisateur,
            ]);

            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ligne === false) {
                throw new RuntimeException('Covoiturage introuvable.');
            }

            if ((string) $ligne['statut_covoiturage'] !== 'EN_COURS') {
                throw new RuntimeException('Terminaison refusée : ce covoiturage n’est pas en cours.');
            }

            /*
             * Le covoiturage passe à TERMINE.
             */
            $stmt = $pdo->prepare("
                UPDATE covoiturage
                SET statut_covoiturage = 'TERMINE'
                WHERE id_covoiturage = :id_covoiturage
                  AND id_utilisateur = :id_utilisateur
                  AND statut_covoiturage = 'EN_COURS'
            ");
            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
                'id_utilisateur' => $idUtilisateur,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Terminaison refusée : mise à jour du statut impossible.');
            }

            /*
             * Les participations actives passent en EN_ATTENTE.
             * Le passager pourra ensuite confirmer le trajet
             * puis déposer son avis.
             */
            $stmt = $pdo->prepare("
                UPDATE participation
                SET statut_validation = 'EN_ATTENTE',
                    commentaire_validation = NULL
                WHERE id_covoiturage = :id_covoiturage
                  AND est_annulee = false
                  AND statut_validation = 'NON_DEMANDEE'
            ");
            $stmt->execute([
                'id_covoiturage' => $idCovoiturage,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Vérifie si l'utilisateur peut déclarer un incident.
     *
     * Conditions :
     * - identifiants valides ;
     * - l'utilisateur doit être le chauffeur du covoiturage ;
     * - le covoiturage doit être en cours ou terminé.
     *
     * Ici, un simple SELECT 1 suffit :
     * on ne cherche pas à charger tout le trajet,
     * seulement à savoir si la règle métier est respectée.
     *
     * @param int $idUtilisateur Identifiant du chauffeur connecté.
     * @param int $idCovoiturage Identifiant du covoiturage ciblé.
     *
     * @return bool
     */
    public function peutDeclarerIncident(int $idUtilisateur, int $idCovoiturage): bool
    {
        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            return false;
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        $stmt = $pdo->prepare("
            SELECT 1
            FROM covoiturage c
            WHERE c.id_covoiturage = :id_covoiturage
              AND c.id_utilisateur = :id_utilisateur
              AND c.statut_covoiturage IN ('EN_COURS', 'TERMINE')
            LIMIT 1
        ");

        $stmt->execute([
            'id_covoiturage' => $idCovoiturage,
            'id_utilisateur' => $idUtilisateur,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Vérifie si l'utilisateur peut donner un avis sur un covoiturage.
     *
     * Conditions :
     * - l'utilisateur doit avoir une participation sur ce trajet ;
     * - la participation ne doit pas être annulée ;
     * - le covoiturage doit être terminé ;
     * - la validation doit être en attente ;
     * - aucun avis ne doit déjà exister pour cette participation.
     *
     * Comme pour peutDeclarerIncident(),
     * un simple SELECT 1 permet ici de répondre oui ou non.
     *
     * @param int $idUtilisateur Identifiant du passager connecté.
     * @param int $idCovoiturage Identifiant du covoiturage ciblé.
     *
     * @return bool
     */
    public function peutDonnerAvis(int $idUtilisateur, int $idCovoiturage): bool
    {
        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            return false;
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        $stmt = $pdo->prepare("
            SELECT 1
            FROM participation p
            JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
            LEFT JOIN avis a ON a.id_participation = p.id_participation
            WHERE p.id_utilisateur = :id_utilisateur
              AND p.id_covoiturage = :id_covoiturage
              AND p.est_annulee = false
              AND c.statut_covoiturage = 'TERMINE'
              AND p.statut_validation = 'EN_ATTENTE'
              AND a.id_avis IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'id_utilisateur' => $idUtilisateur,
            'id_covoiturage' => $idCovoiturage,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Enregistre la satisfaction du passager et crée un avis.
     *
     * Cette méthode vérifie d'abord :
     * - les paramètres ;
     * - la note ;
     * - la longueur du commentaire ;
     * - le droit réel du passager à déposer un avis sur ce trajet.
     *
     * Si tout est valide :
     * - un avis est créé avec modération EN_ATTENTE ;
     * - la participation passe en validation OK.
     *
     * La transaction garde ces deux écritures cohérentes :
     * il ne faut pas créer un avis sans mettre à jour la validation,
     * ni valider une participation sans avoir créé l'avis attendu.
     *
     * @param int $idUtilisateur Identifiant du passager connecté.
     * @param int $idCovoiturage Identifiant du covoiturage concerné.
     * @param int $note Note donnée par le passager.
     * @param string $commentaire Commentaire laissé par le passager.
     *
     * @return void
     */
    public function enregistrerSatisfaction(int $idUtilisateur, int $idCovoiturage, int $note, string $commentaire): void
    {
        /*
         * Le commentaire est nettoyé avant validation.
         * Cela évite d'accepter une chaîne vide maquillée avec des espaces.
         */
        $commentaire = trim($commentaire);

        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            throw new RuntimeException('Enregistrement impossible : paramètres invalides.');
        }

        if ($note < 1 || $note > 5) {
            throw new RuntimeException('Enregistrement refusé : note invalide (1 à 5).');
        }

        if ($commentaire !== '' && mb_strlen($commentaire) > 1000) {
            throw new RuntimeException('Enregistrement refusé : commentaire trop long (1000 caractères max).');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            /*
             * On relit et on verrouille la participation concernée.
             * Cela garantit que le droit à déposer un avis
             * reste stable pendant tout le traitement.
             */
            $stmt = $pdo->prepare("
                SELECT p.id_participation
                FROM participation p
                JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
                WHERE p.id_utilisateur = :id_utilisateur
                  AND p.id_covoiturage = :id_covoiturage
                  AND p.est_annulee = false
                  AND c.statut_covoiturage = 'TERMINE'
                  AND p.statut_validation = 'EN_ATTENTE'
                FOR UPDATE
                LIMIT 1
            ");

            $stmt->execute([
                'id_utilisateur' => $idUtilisateur,
                'id_covoiturage' => $idCovoiturage,
            ]);

            $idParticipation = (int) ($stmt->fetchColumn() ?: 0);
            if ($idParticipation <= 0) {
                throw new RuntimeException("Enregistrement refusé : vous ne pouvez pas donner d'avis sur ce trajet.");
            }

            /*
             * Vérification complémentaire :
             * une participation ne doit recevoir qu'un seul avis.
             *
             * On refait le contrôle explicitement ici
             * avant l'insertion pour garder un message métier clair.
             */
            $stmt = $pdo->prepare("
                SELECT 1
                FROM avis
                WHERE id_participation = :id_participation
                LIMIT 1
            ");
            $stmt->execute([
                'id_participation' => $idParticipation,
            ]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException("Enregistrement refusé : un avis a déjà été déposé pour ce trajet.");
            }

            /*
             * Création de l'avis.
             * Le statut de modération démarre en EN_ATTENTE
             * parce qu'un employé doit encore relire cet avis.
             */
            $stmt = $pdo->prepare("
                INSERT INTO avis (note, commentaire, date_depot, statut_moderation, id_participation, id_employe_moderateur)
                VALUES (:note, :commentaire, NOW(), 'EN_ATTENTE', :id_participation, NULL)
            ");

            $stmt->execute([
                'note' => $note,
                'commentaire' => $commentaire === '' ? null : $commentaire,
                'id_participation' => $idParticipation,
            ]);

            /*
             * Une fois l'avis créé,
             * la participation passe en validation OK.
             */
            $stmt = $pdo->prepare("
                UPDATE participation
                SET statut_validation = 'OK',
                    commentaire_validation = NULL
                WHERE id_participation = :id_participation
            ");
            $stmt->execute([
                'id_participation' => $idParticipation,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Enregistrement refusé : mise à jour de la validation impossible.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();

            /*
             * Sécurité complémentaire :
             * si la base détecte malgré tout un doublon sur l'avis,
             * on transforme l'erreur technique en message métier compréhensible.
             */
            $message = $e->getMessage();
            if (
                stripos($message, 'uq_avis_participation') !== false
                || stripos($message, 'duplicate key') !== false
                || stripos($message, 'unique') !== false
            ) {
                throw new RuntimeException("Enregistrement refusé : un avis a déjà été déposé pour ce trajet.");
            }

            throw $e;
        }
    }
}
