<?php

declare(strict_types=1);

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

final class PersistanceHistoriquePostgresql
{
    public function __construct(private ConnexionPostgresql $connexionPostgresql)
    {
    }

    /**
     * Lister "Mes covoiturages publiés" 
     * @return array<int, array<string, mixed>>
     */
    public function listerCovoituragesPublies(int $idUtilisateur): array
    {
        if ($idUtilisateur <= 0) {
            throw new RuntimeException('ID utilisateur invalide.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
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
     * Lister "Mes participations" 
     * @return array<int, array<string, mixed>>
     */
    public function listerParticipations(int $idUtilisateur): array
    {
        if ($idUtilisateur <= 0) {
            throw new RuntimeException('ID utilisateur invalide.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
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
            ORDER BY c.date_heure_depart DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_utilisateur' => $idUtilisateur]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Annuler une participation
     */
    public function annulerParticipation(int $idUtilisateur, int $idParticipation): void
    {
        if ($idUtilisateur <= 0 || $idParticipation <= 0) {
            throw new RuntimeException('Annulation impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT p.est_annulee, c.statut_covoiturage
                FROM participation p
                JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
                WHERE p.id_participation = :id_participation
                AND p.id_utilisateur = :id_utilisateur
                LIMIT 1
            ");

            $stmt->execute(['id_participation' => $idParticipation, 'id_utilisateur' => $idUtilisateur]);

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
     * Annuler un covoiturage
     */
    public function annulerCovoiturage(int $idUtilisateur, int $idCovoiturage): void
    {
        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            throw new RuntimeException('Annulation impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT statut_covoiturage
                FROM covoiturage
                WHERE id_covoiturage = :id_covoiturage
                AND id_utilisateur = :id_utilisateur
                LIMIT 1
            ");

            $stmt->execute(['id_covoiturage' => $idCovoiturage, 'id_utilisateur' => $idUtilisateur]);

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

            // Annuler le covoiturage et les participations
            $stmt = $pdo->prepare("
                UPDATE covoiturage
                SET statut_covoiturage = 'ANNULE'
                WHERE id_covoiturage = :id_covoiturage
            ");
            $stmt->execute(['id_covoiturage' => $idCovoiturage]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Annulation refusée : mise à jour du covoiturage impossible.');
            }

            // Annuler les participations actives
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
     * Lister les emails des participants d’un covoiturage (pour courriels chauffeur).
     *
     * Règles :
     * - sécurité : le covoiturage doit appartenir au chauffeur
     * - on ne prend que les participations actives
     * - on renvoie des emails uniques
     *
     * @return array<int, string>
     */
    public function listerEmailsParticipants(int $idUtilisateur, int $idCovoiturage): array
    {
        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            throw new RuntimeException('Paramètres invalides pour la liste des emails participants.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
        SELECT DISTINCT u.email
        FROM participation p
        JOIN utilisateur u ON u.id_utilisateur = p.id_utilisateur
        JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
        WHERE c.id_covoiturage = :id_covoiturage
          AND c.id_utilisateur = :id_utilisateur
          AND p.est_annulee = false
        ORDER BY u.email ASC
    ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_covoiturage' => $idCovoiturage,
            'id_utilisateur' => $idUtilisateur,
        ]);

        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // Nettoyage simple 
        $emailsNettoyes = [];
        foreach ($emails as $email) {
            $email = trim((string) $email);
            if ($email !== '') {
                $emailsNettoyes[] = $email;
            }
        }

        return $emailsNettoyes;
    }


    public function declarerIncident(int $idUtilisateur, int $idCovoiturage, string $commentaire): void
    {
        /*
          Objectif :
          - déclarer un incident sur un covoiturage
          - commentaire obligatoire
          - chauffeur uniquement 
          - autorisé si EN_COURS ou TERMINE
          - interdit si déjà en INCIDENT
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
            // 1) Vérifier existence + propriété + statut
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

            // 2) Déjà en incident 
            if ($statutCovoiturage === 'INCIDENT') {
                throw new RuntimeException('Incident refusé : un incident est déjà déclaré.');
            }

            // 3) Statut éligible 
            if (!in_array($statutCovoiturage, ['EN_COURS', 'TERMINE'], true)) {
                throw new RuntimeException(
                    'Incident refusé : ce covoiturage n’est pas éligible (en cours ou terminé uniquement).'
                );
            }

            // 4) Passage en INCIDENT + commentaire
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


    public function demarrerCovoiturage(int $idUtilisateur, int $idCovoiturage): void
    {
        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            throw new RuntimeException('Terminaison impossible : paramètres invalides.');
        }


        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            // Vérification du statut actuel
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

            // Mise à jour du statut en EN_COURS
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
     * Terminer un covoiturage
     * - Chauffeur uniquement
     * - EN_COURS à TERMINE
     * - Passe les participations actives en EN_ATTENTE pour avis et validation
     */
    public function terminerCovoiturage(int $idUtilisateur, int $idCovoiturage): void
    {
        if ($idUtilisateur <= 0 || $idCovoiturage <= 0) {
            throw new RuntimeException('Terminaison impossible : paramètres invalides.');
        }

        $pdo = $this->connexionPostgresql->obtenirPdo();
        $pdo->beginTransaction();

        try {
            // 1) Vérifier existence + propriété + statut 
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

            // 2) Passer le covoiturage à TERMINE
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

            // 3) Demander la validation côté passagers sur participation active
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
     * Vérifier si l'utilisateur peut déclarer un incident sur ce covoiturage.
     * - il doit être le chauffeur donc est propriétaire du covoiturage.
     * - le covoiturage doit être en cours ou terminé.
     * - éviter les ids invalides.
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
     * Vérifier si l'utilisateur peut donner un avis sur ce covoiturage.
     * - l'utilisateur doit avoir une participation sur ce covoiturage
     * - participation non annulée
     * - covoiturage terminé
     * - validation en attente
     * - aucun avis déjà déposé pour cette participation
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

    public function enregistrerSatisfaction(int $idUtilisateur, int $idCovoiturage, int $note, string $commentaire): void
    {

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
              1) Vérifier le droit + verrouiller la participation 
                 - uniquement si : covoiturage terminé + participation en attente et non annulée
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
              2) Sécurité supplémentaire = un avis par participation 
                 - on revérifie explicitement 
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
              3) Créer l'avis et modération en attente
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
              4) Marquer la participation comme validée 
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

            /* 1 avis maximum par participation.
            - Si on essaie d’enregistrer un avis alors qu’il existe déjà, la bdd renvoie une erreur.
            - on transforme cette erreur technique en message compréhensible pour l’utilisateur */
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
