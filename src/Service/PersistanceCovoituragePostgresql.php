<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PersistanceCovoituragePostgresql
{
  /*
    PLAN (PersistanceCovoituragePostgresql) :

    1) Rôle de ce service
       - pont entre Symfony et PostgreSQL pour les covoiturages
       - le SQL reste ici (pas dans les contrôleurs)

    2) Ce que je gère dans ce fichier
       - recherche de covoiturages (avec filtres)
       - détail d’un covoiturage (chauffeur + voiture + note moyenne)
       - liste d’avis valides (pour afficher sur la page détail)
       - création d’un covoiturage planifié (publication)

    3) Principe important
       - la base est la source de vérité
       - requêtes préparées pour éviter les injections
       - cohérence : statuts et règles TP (PLANIFIE, places > 0, etc.)
  */

  public function __construct(private ConnexionPostgresql $connexionPostgresql)
  {
  }

  /**
   * Recherche de covoiturages PLANIFIE avec places dispo.
   * Les paramètres optionnels peuvent être null.
   *
   * @return array<int, array<string, mixed>>
   */
  public function rechercherCovoiturages(
    string $villeDepart,
    string $villeArrivee,
    DateTimeImmutable $date,
    ?string $heureMin,
    ?string $heureMax,
    ?int $prixMax,
    ?string $energie,
    ?int $ageMaxVoiture,
    ?int $noteMin,
  ): array {
    /*
      PLAN (rechercherCovoiturages) :

      1) Construire une plage de date/heure (début/fin)
      2) Requête socle
      3) Ajouter les filtres si demandés
      4) Exécuter et renvoyer les résultats
    */

    $pdo = $this->connexionPostgresql->obtenirPdo();

    // 1) Plage journée complète par défaut
    $debut = $date->setTime(0, 0);
    $fin = $date->setTime(23, 59, 59);

    if (null !== $heureMin && preg_match('/^\d{2}:\d{2}$/', $heureMin)) {
      [$h, $m] = array_map('intval', explode(':', $heureMin));
      $debut = $date->setTime($h, $m);
    }

    if (null !== $heureMax && preg_match('/^\d{2}:\d{2}$/', $heureMax)) {
      [$h, $m] = array_map('intval', explode(':', $heureMax));
      $fin = $date->setTime($h, $m);
    }

    if ($fin < $debut) {
      [$debut, $fin] = [$fin, $debut];
    }

    /*
      2) Requête socle

      Important :
      - PLANIFIE
      - places dispo > 0
      - villes
      - date_heure_depart dans [début, fin]
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

      Point important
      - ILIKE avec % = recherche “contient”, insensible à la casse
    */
    $parametres = [
      'ville_depart' => '%' . trim($villeDepart) . '%',
      'ville_arrivee' => '%' . trim($villeArrivee) . '%',
      'debut' => $debut->format('Y-m-d H:i:s'),
      'fin' => $fin->format('Y-m-d H:i:s'),
    ];

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
      $sql .= " AND voiture.date_1ere_mise_en_circulation >= (CURRENT_DATE - (:age_max * INTERVAL '1 year'))";
      $parametres['age_max'] = $ageMaxVoiture;
    }

    if (null !== $noteMin) {
      $sql .= ' AND COALESCE(note.note_moyenne, 0) >= :note_min';
      $parametres['note_min'] = $noteMin;
    }

    $sql .= ' ORDER BY covoiturage.date_heure_depart ASC';

    $requete = $pdo->prepare($sql);
    $requete->execute($parametres);

    return $requete->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Détail d'un covoiturage par id.
   *
   * @return array<string, mixed>|null
   */
  public function obtenirDetailCovoiturageParId(int $idCovoiturage): ?array
  {
    if ($idCovoiturage <= 0) {
      return null;
    }

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

                c.est_non_fumeur,
                c.accepte_animaux,
                c.preferences_libre,

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

    $ligne = $requete->fetch(PDO::FETCH_ASSOC);

    return false !== $ligne ? $ligne : null;
  }

  /**
   * Avis validés liés à un chauffeur (via ses covoiturages).
   *
   * @return array<int, array<string, mixed>>
   */
  public function obtenirAvisValidesDuChauffeur(int $idChauffeur, int $limite = 5): array
  {
    if ($idChauffeur <= 0) {
      return [];
    }

    // Garde-fou simple
    $limite = max(1, min(20, (int) $limite));

    $pdo = $this->connexionPostgresql->obtenirPdo();

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
              AND p.est_annulee = false
            ORDER BY a.date_depot DESC
            LIMIT :limite
        ";

    $requete = $pdo->prepare($sql);
    $requete->bindValue('id_chauffeur', $idChauffeur, PDO::PARAM_INT);
    $requete->bindValue('limite', $limite, PDO::PARAM_INT);
    $requete->execute();

    return $requete->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
  public function creerCovoituragePlanifie(
    int $idUtilisateur,
    int $idVoiture,
    DateTimeImmutable $dateHeureDepart,
    DateTimeImmutable $dateHeureArrivee,
    string $adresseDepart,
    string $adresseArrivee,
    string $villeDepart,
    string $villeArrivee,
    int $nbPlacesDispo,
    int $prixCredits,
    bool $estNonFumeur,
    bool $accepteAnimaux,
    ?string $preferenceLibre
  ): int {
    /*
      PLAN (creerCovoituragePlanifie) :

      1) Valider les paramètres minimaux (id, dates, texte)
      2) Construire l’INSERT aligné avec la BDD
      3) Exécuter et retourner l’id
    */

    if ($idUtilisateur <= 0 || $idVoiture <= 0) {
      throw new RuntimeException('Création impossible : paramètres invalides.');
    }

    if ($dateHeureArrivee <= $dateHeureDepart) {
      throw new RuntimeException('Création impossible : la date d’arrivée doit être après le départ.');
    }

    $adresseDepart = trim($adresseDepart);
    $adresseArrivee = trim($adresseArrivee);
    $villeDepart = trim($villeDepart);
    $villeArrivee = trim($villeArrivee);

    if ($adresseDepart === '' || $adresseArrivee === '' || $villeDepart === '' || $villeArrivee === '') {
      throw new RuntimeException('Création impossible : adresse/ville manquante.');
    }

    if ($nbPlacesDispo < 1 || $nbPlacesDispo > 4) {
      throw new RuntimeException('Création impossible : nombre de places invalide.');
    }

    if ($prixCredits <= 0) {
      throw new RuntimeException('Création impossible : prix invalide.');
    }

    $preferenceLibre = null !== $preferenceLibre ? trim($preferenceLibre) : null;
    if ($preferenceLibre === '') {
      $preferenceLibre = null;
    }

    $pdo = $this->connexionPostgresql->obtenirPdo();

    $sql = "
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

                est_non_fumeur,
                accepte_animaux,
                preferences_libre,

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

                :est_non_fumeur,
                :accepte_animaux,
                :preferences_libre,

                :id_utilisateur,
                :id_voiture
            )
            RETURNING id_covoiturage
        ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'date_heure_depart' => $dateHeureDepart->format('Y-m-d H:i:s'),
      'date_heure_arrivee' => $dateHeureArrivee->format('Y-m-d H:i:s'),
      'adresse_depart' => $adresseDepart,
      'adresse_arrivee' => $adresseArrivee,
      'ville_depart' => $villeDepart,
      'ville_arrivee' => $villeArrivee,
      'nb_places_dispo' => $nbPlacesDispo,
      'prix_credits' => $prixCredits,

      'est_non_fumeur' => $estNonFumeur ? 1 : 0,
      'accepte_animaux' => $accepteAnimaux ? 1 : 0,
      'preferences_libre' => $preferenceLibre,

      'id_utilisateur' => $idUtilisateur,
      'id_voiture' => $idVoiture,
    ]);

    $id = $stmt->fetchColumn();
    if ($id === false) {
      throw new RuntimeException('Création impossible : aucun id de covoiturage retourné.');
    }

    return (int) $id;
  }
}