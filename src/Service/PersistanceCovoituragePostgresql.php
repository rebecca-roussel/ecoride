<?php

declare(strict_types=1);

namespace App\Service;

final class PersistanceCovoituragePostgresql
{
    /*
      PLAN (PersistanceCovoituragePostgresql) :

      1) Rôle de ce service
         - c’est mon “pont” entre Symfony et PostgreSQL pour tout ce qui concerne les covoiturages
         - je garde le SQL ici, pas dans les contrôleurs

      2) Ce que je gère dans ce fichier
         - recherche de covoiturages (avec filtres)
         - détail d’un covoiturage (chauffeur + voiture + note moyenne)
         - liste d’avis valides (pour afficher sur la page détail)

      3) Principe important
         - la base est la source de vérité
         - je filtre sur les statuts demandés par le TP (PLANIFIE, places disponibles, etc.)
         - j’utilise des requêtes préparées pour éviter les injections SQL
    */

    public function __construct(private ConnexionPostgresql $connexionPostgresql)
    {
    }

    /**
     * Recherche de covoiturages PLANIFIE avec places dispo.
     * Tous les paramètres optionnels peuvent être null.
     */
    public function rechercherCovoiturages(
        string $villeDepart,
        string $villeArrivee,
        \DateTimeImmutable $date,
        ?string $heureMin,
        ?string $heureMax,
        ?int $prixMax,
        ?string $energie,
        ?int $ageMaxVoiture,
        ?int $noteMin,
    ): array {
        /*
          PLAN (rechercherCovoiturages) :

          1) Construire une plage de date/heure
             - base : de 00:00 à 23:59:59 (toute la journée)
             - si heureMin est donnée : je commence à cette heure
             - si heureMax est donnée : je termine à cette heure
             - si l’utilisateur inverse (fin < début) : je remets dans le bon ordre

          2) Construire la requête SQL
             - on part d’une requête “socle” (statut PLANIFIE, places dispo, villes, date entre début/fin)
             - puis on ajoute des filtres seulement si l’utilisateur en a demandé

          3) Exécuter et renvoyer la liste de résultats
        */

        $pdo = $this->connexionPostgresql->obtenirPdo();

        // 1) Plage de la journée complète par défaut
        $debut = $date->setTime(0, 0);
        $fin = $date->setTime(23, 59, 59);

        // Si l'utilisateur a indiqué une heure min, je la prends
        if (null !== $heureMin && preg_match('/^\d{2}:\d{2}$/', $heureMin)) {
            [$h, $m] = array_map('intval', explode(':', $heureMin));
            $debut = $date->setTime($h, $m);
        }

        // Si l'utilisateur a indiqué une heure max, je la prends
        if (null !== $heureMax && preg_match('/^\d{2}:\d{2}$/', $heureMax)) {
            [$h, $m] = array_map('intval', explode(':', $heureMax));
            $fin = $date->setTime($h, $m);
        }

        // Sécurité logique : si la fin est avant le début, j'échange
        if ($fin < $debut) {
            [$debut, $fin] = [$fin, $debut];
        }

        /*
          2) Requête SQL socle

          - covoiturage : infos du trajet
          - utilisateur : infos chauffeur (pseudo + photo)
          - voiture : énergie + date 1ère mise en circulation (pour l’âge)
          - note : sous-requête qui calcule note moyenne + nombre d’avis valides par chauffeur

          Important :
          - statut PLANIFIE
          - nb_places_dispo > 0
          - villes départ/arrivée
          - date_heure_depart dans la plage début/fin
        */
        $sql = "
            SELECT
                covoiturage.id_covoiturage,
                covoiturage.date_heure_depart,
                covoiturage.date_heure_arrivee,
                covoiturage.ville_depart,
                covoiturage.ville_arrivee,
                covoiturage.adresse_depart,
                covoiturage.adresse_arrivee,
                covoiturage.latitude_depart,
                covoiturage.longitude_depart,
                covoiturage.latitude_arrivee,
                covoiturage.longitude_arrivee,

                covoiturage.nb_places_dispo,
                covoiturage.prix_credits,
                covoiturage.commission_credits,
                covoiturage.statut_covoiturage,

                utilisateur.id_utilisateur AS id_chauffeur,
                utilisateur.pseudo AS pseudo_chauffeur,
                utilisateur.photo_path AS photo_chauffeur,

                voiture.energie,
                voiture.date_1ere_mise_en_circulation,

                note.note_moyenne,
                note.nb_avis_valides

            FROM covoiturage
            JOIN utilisateur
              ON utilisateur.id_utilisateur = covoiturage.id_utilisateur
            JOIN voiture
              ON voiture.id_voiture = covoiturage.id_voiture
             AND voiture.id_utilisateur = covoiturage.id_utilisateur

            LEFT JOIN (
                SELECT
                    covoiturage.id_utilisateur AS id_chauffeur,
                    ROUND(AVG(avis.note)::numeric, 2) AS note_moyenne,
                    COUNT(*) AS nb_avis_valides
                FROM avis
                JOIN participation
                  ON participation.id_participation = avis.id_participation
                 AND participation.est_annulee = false
                JOIN covoiturage
                  ON covoiturage.id_covoiturage = participation.id_covoiturage
                WHERE avis.statut_moderation = 'VALIDE'
                GROUP BY covoiturage.id_utilisateur
            ) AS note
              ON note.id_chauffeur = utilisateur.id_utilisateur

            WHERE covoiturage.statut_covoiturage = 'PLANIFIE'
              AND covoiturage.nb_places_dispo > 0
              AND covoiturage.ville_depart ILIKE :ville_depart
              AND covoiturage.ville_arrivee ILIKE :ville_arrivee
              AND covoiturage.date_heure_depart BETWEEN :debut AND :fin
        ";

        /*
          Paramètres de base
          - villes : on “trim” au cas où
          - dates : on envoie en string pour PostgreSQL (format clair)
        */
        $parametres = [
            'ville_depart' => trim($villeDepart),
            'ville_arrivee' => trim($villeArrivee),
            'debut' => $debut->format('Y-m-d H:i:s'),
            'fin' => $fin->format('Y-m-d H:i:s'),
        ];

        // Filtres optionnels (je n’ajoute la condition que si le filtre existe)

        if (null !== $prixMax) {
            $sql .= ' AND covoiturage.prix_credits <= :prix_max';
            $parametres['prix_max'] = $prixMax;
        }

        if (null !== $energie && '' !== $energie) {
            $energie = strtoupper(trim($energie));
            $sql .= ' AND voiture.energie = :energie';
            $parametres['energie'] = $energie;
        }

        if (null !== $ageMaxVoiture) {
            /*
              Age max voiture
              - on veut des voitures “pas plus vieilles que X ans”
              - donc : date_1ere_mise_en_circulation >= CURRENT_DATE - X années
            */
            $sql .= " AND voiture.date_1ere_mise_en_circulation >= (CURRENT_DATE - (:age_max * INTERVAL '1 year'))";
            $parametres['age_max'] = $ageMaxVoiture;
        }

        if (null !== $noteMin) {
            // COALESCE : si pas de note (null), on considère 0
            $sql .= ' AND COALESCE(note.note_moyenne, 0) >= :note_min';
            $parametres['note_min'] = $noteMin;
        }

        // Tri : les trajets du plus tôt au plus tard
        $sql .= ' ORDER BY covoiturage.date_heure_depart ASC';

        $requete = $pdo->prepare($sql);
        $requete->execute($parametres);

        return $requete->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Détail d'un covoiturage par id (chauffeur + voiture + note).
     * Retourne null si l'id n'existe pas.
     */
    public function obtenirDetailCovoiturageParId(int $idCovoiturage): ?array
    {
        /*
          PLAN (obtenirDetailCovoiturageParId) :

          - je récupère un seul covoiturage par son id
          - je joins le chauffeur (utilisateur) et la voiture
          - je calcule la note moyenne + nb d’avis valides (même principe que la recherche)
          - je retourne null si rien trouvé
        */

        $pdo = $this->connexionPostgresql->obtenirPdo();

        $sql = "
            SELECT
                c.id_covoiturage,
                c.date_heure_depart,
                c.date_heure_arrivee,
                c.ville_depart,
                c.ville_arrivee,
                c.adresse_depart,
                c.adresse_arrivee,
                c.latitude_depart,
                c.longitude_depart,
                c.latitude_arrivee,
                c.longitude_arrivee,

                c.nb_places_dispo,
                c.prix_credits,
                c.commission_credits,
                c.statut_covoiturage,

                u.id_utilisateur AS id_chauffeur,
                u.pseudo AS pseudo_chauffeur,
                u.photo_path AS photo_chauffeur,

                v.marque,
                v.couleur,
                v.energie,
                v.date_1ere_mise_en_circulation,

                note.note_moyenne,
                COALESCE(note.nb_avis_valides, 0) AS nb_avis_valides

            FROM covoiturage c
            JOIN utilisateur u
              ON u.id_utilisateur = c.id_utilisateur
            JOIN voiture v
              ON v.id_voiture = c.id_voiture
             AND v.id_utilisateur = c.id_utilisateur

            LEFT JOIN (
                SELECT
                    covoiturage.id_utilisateur AS id_chauffeur,
                    ROUND(AVG(avis.note)::numeric, 2) AS note_moyenne,
                    COUNT(*) AS nb_avis_valides
                FROM avis
                JOIN participation
                  ON participation.id_participation = avis.id_participation
                 AND participation.est_annulee = false
                JOIN covoiturage
                  ON covoiturage.id_covoiturage = participation.id_covoiturage
                WHERE avis.statut_moderation = 'VALIDE'
                GROUP BY covoiturage.id_utilisateur
            ) AS note
              ON note.id_chauffeur = u.id_utilisateur

            WHERE c.id_covoiturage = :id_covoiturage
            LIMIT 1
        ";

        $requete = $pdo->prepare($sql);
        $requete->execute(['id_covoiturage' => $idCovoiturage]);

        $ligne = $requete->fetch(\PDO::FETCH_ASSOC);

        return false !== $ligne ? $ligne : null;
    }

    /*
      Avis valides du chauffeur
      - utile pour afficher les derniers avis sur la page détail
      - limite configurable (par défaut 5)
    */
    public function obtenirAvisValidesDuChauffeur(int $idChauffeur, int $limite = 5): array
    {
        $pdo = $this->connexionPostgresql->obtenirPdo();

        // Sécurité : je borne la limite (évite les valeurs absurdes)
        $limite = max(1, min(20, (int) $limite));

        /*
          Je récupère :
          - note, commentaire, date
          - pseudo de l'auteur (le passager qui a laissé l’avis)

          Je ne prends que :
          - avis validés
          - participation non annulée (sinon ça n’aurait pas de sens)
        */
        $sql = "
            SELECT
                a.note,
                a.commentaire,
                a.date_depot,
                u.pseudo AS pseudo_auteur
            FROM avis a
            JOIN participation p ON p.id_participation = a.id_participation
            JOIN covoiturage c ON c.id_covoiturage = p.id_covoiturage
            JOIN utilisateur u ON u.id_utilisateur = p.id_utilisateur
            WHERE c.id_utilisateur = :id_chauffeur
              AND a.statut_moderation = 'VALIDE'
            ORDER BY a.date_depot DESC
            LIMIT {$limite}
        ";

        $requete = $pdo->prepare($sql);

        // bindValue en int pour être sûre du type
        $requete->bindValue('id_chauffeur', $idChauffeur, \PDO::PARAM_INT);

        $requete->execute();

        return $requete->fetchAll(\PDO::FETCH_ASSOC);
    }
}
