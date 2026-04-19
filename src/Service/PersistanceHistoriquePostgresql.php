<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Service de persistance PostgreSQL pour l'historique utilisateur.
 *
 * Cette classe centralise les lectures et écritures liées à l'espace historique :
 * - covoiturages publiés par le chauffeur ;
 * - participations du passager ;
 * - annulation de participation ;
 * - annulation de covoiturage ;
 * - démarrage et terminaison d'un covoiturage ;
 * - déclaration d'incident ;
 * - vérification du droit à déclarer un incident ;
 * - vérification du droit à déposer un avis ;
 * - enregistrement d'une satisfaction.
 *
 * Le service garde ici les requêtes SQL et les transactions.
 * Les contrôleurs, eux, restent concentrés sur le parcours HTTP,
 * les messages flash, la lecture de la requête et les redirections.
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
     * On récupère les informations utiles à l'affichage du tableau de bord :
     * ville de départ, ville d'arrivée, date, places, prix, statut
     * et commentaire d'incident si un incident a été déclaré.
     *
     * Le tri reste ici en ordre décroissant sur la date de départ :
     * les trajets les plus récents ou les plus lointains apparaissent d'abord.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur connecté.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listerCovoituragesPublies(int $idUtilisateur): array
    {
        /*
         * La méthode travaille uniquement avec un identifiant utilisateur valide.
         * Cela évite de lancer une requête sans cible cohérente.
         */
        if ($idUtilisateur <= 0) {
            throw new RuntimeException('ID utilisateur invalide.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
         * On lit uniquement les covoiturages dont l'utilisateur connecté
         * est le chauffeur propriétaire.
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
            ORDER BY c.date_heure_depart DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_utilisateur' => $idUtilisateur]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Liste les participations de l'utilisateur connecté.
     *
     * Cette lecture alimente l'onglet "Mes participations".
     * On joint la participation au covoiturage afin d'obtenir le contexte utile :
     * trajet, date, prix, statut du covoiturage et état de validation.
     *
     * Le tri est volontairement fait en ordre croissant sur la date de départ.
     * Cela permet d'afficher d'abord les trajets les plus proches,
     * ce qui est plus cohérent pour un passager.
     *
     * @param int $idUtilisateur Identifiant de l'utilisateur connecté.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listerParticipations(int $idUtilisateur): array
    {
        /*
         * Comme pour les autres lectures, on refuse un identifiant invalide.
         */
        if ($idUtilisateur <= 0) {
            throw new RuntimeException('ID utilisateur invalide.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        /*
         * On récupère les participations de l'utilisateur,
         * puis les informations du covoiturage lié.
         *
         * Correction importante :
         * le tri passe en ASC pour afficher d'abord les trajets les plus proches.
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
        $stmt->execute(['id_utilisateur' => $idUtilisateur]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Annule une participation.
     *
     * Cette méthode vérifie d'abord :
     * - que la participation existe ;
     * - qu'elle appartient bien à l'utilisateur connecté ;
     * - qu'elle n'est pas déjà annulée ;
     * - que le covoiturage reste encore annulable.
     *
     * Une transaction est utilisée pour garder l'opération cohérente :
     * soit tout passe, soit rien n'est appliqué.
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
             * On relit la participation avec le statut du covoiturage
             * pour vérifier que l'utilisateur agit bien sur sa propre participation
             * et que le trajet est encore annulable.
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
            if (in_array($statutCovoiturage, ['TERMINE', 'ANNULE'], true)) {
                throw new RuntimeException('Annulation refusée : ce trajet n’est plus annulable.');
            }

            /*
             * On annule la participation.
             * Le statut de validation est remis à NON_DEMANDEE
             * car la participation sort du parcours.
             */
            $stmt = $pdo->prepare("
                UPDATE participation
                SET est_annulee = true, statut_validation = 'NON_DEMANDEE', commentaire_validation = NULL
                WHERE id_participation = :id_participation
            ");
            $stmt->execute(['id_participation' => $idParticipation]);

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
     * Cette méthode vérifie :
     * - que le covoiturage existe ;
     * - qu'il appartient bien à l'utilisateur connecté ;
     * - qu'il n'est ni déjà annulé, ni terminé, ni en cours.
     *
     * Si tout est cohérent :
     * - le covoiturage passe à ANNULE ;
     * - les participations actives sont aussi annulées.
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
             * et qu'il reste encore annulable.
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
             * On annule le covoiturage lui-même.
             */
            $stmt = $pdo->prepare("
                UPDATE covoiturage
                SET statut_covoiturage = 'ANNULE'
                WHERE id_covoiturage = :id_covoiturage
            ");
            $stmt->execute(['id_covoiturage' => $idCovoiturage]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Annulation refusée : mise à jour du covoiturage impossible.');
            }

            /*
             * On annule ensuite toutes les participations encore actives sur ce trajet.
             */
            $stmt = $pdo->prepare("
                UPDATE participation
                SET est_annulee = true, statut_validation = 'NON_DEMANDEE', commentaire_validation = NULL
                WHERE id_covoiturage = :id_covoiturage AND est_annulee = false
            ");
            $stmt->execute(['id_covoiturage' => $idCovoiturage]);

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
     * Un trajet déjà en INCIDENT ne peut pas recevoir un second incident.
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
         * Le commentaire est nettoyé avant validation
         * pour éviter qu'une chaîne remplie uniquement d'espaces
         * soit acceptée comme contenu réel.
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
             * On verrouille d'abord le covoiturage concerné.
             * Cela évite qu'un autre traitement change le statut
             * pendant qu'on vérifie les règles métier.
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
             * Si toutes les conditions sont respectées,
             * le covoiturage passe en statut INCIDENT
             * et le commentaire est stocké.
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
     * Le covoiturage doit être encore en statut PLANIFIE.
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
             * et que le statut actuel permet le démarrage.
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
             * Le trajet passe en EN_COURS.
             */
            $stmt = $pdo->prepare("
                UPDATE covoiturage
                SET statut_covoiturage = 'EN_COURS'
                WHERE id_covoiturage = :id_covoiturage
            ");
            $stmt->execute(['id_covoiturage' => $idCovoiturage]);

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
     * Le trajet doit être en cours.
     * Une fois terminé, les participations actives passent en EN_ATTENTE
     * pour permettre ensuite la validation et le dépôt d'un avis.
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
             * On verrouille le covoiturage concerné
             * pour stabiliser l'état lu pendant la transaction.
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
             * Cela ouvre ensuite le parcours de validation côté passager.
             */
            $stmt = $pdo->prepare("
                UPDATE participation
                SET statut_validation = 'EN_ATTENTE',
                    commentaire_validation = NULL
                WHERE id_covoiturage = :id_covoiturage
                  AND est_annulee = false
                  AND statut_validation = 'NON_DEMANDEE'
            ");
            $stmt->execute(['id_covoiturage' => $idCovoiturage]);

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

        /*
         * On teste simplement l'existence d'une ligne correspondante.
         * Aucun chargement complet n'est nécessaire ici.
         */
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

        /*
         * Comme pour peutDeclarerIncident(),
         * un simple SELECT 1 suffit pour répondre oui ou non.
         */
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
     * La transaction garantit que ces deux étapes restent cohérentes.
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
         * Le commentaire est nettoyé avant validation,
         * comme pour la déclaration d'incident.
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
             * Cela garantit que le droit à déposer un avis reste stable
             * pendant le traitement.
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
             * On refait une vérification explicite :
             * une participation ne doit jamais recevoir plusieurs avis.
             */
            $stmt = $pdo->prepare("
                SELECT 1
                FROM avis
                WHERE id_participation = :id_participation
                LIMIT 1
            ");
            $stmt->execute(['id_participation' => $idParticipation]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException("Enregistrement refusé : un avis a déjà été déposé pour ce trajet.");
            }

            /*
             * Création de l'avis.
             * Le statut de modération démarre en EN_ATTENTE.
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
             * Une fois l'avis créé, la participation passe en validation OK.
             */
            $stmt = $pdo->prepare("
                UPDATE participation
                SET statut_validation = 'OK',
                    commentaire_validation = NULL
                WHERE id_participation = :id_participation
            ");
            $stmt->execute(['id_participation' => $idParticipation]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Enregistrement refusé : mise à jour de la validation impossible.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();

            /*
             * Sécurité complémentaire :
             * si la base détecte tout de même un doublon sur l'avis,
             * on transforme l'erreur technique en message compréhensible.
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
