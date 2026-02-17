--ROLLBACK;

BEGIN;
/*
  Script d'insertion de données de démonstration BDD EcoRide
  Script rejouable
*/

TRUNCATE TABLE
  commission_plateforme,
  avis,
  participation,
  covoiturage,
  voiture,
  employe,
  administrateur,
  utilisateur
RESTART IDENTITY CASCADE;

/*
   1) UTILISATEURS
   - mot_de_passe_hash : longueur 60 (bcrypt simulé)
   - IMPORTANT : les comptes internes (employé/admin) doivent avoir role_interne=true
     dès l'INSERT utilisateur, sinon la contrainte ck_utilisateur_au_moins_un_role bloque.
 */

INSERT INTO utilisateur (
  pseudo, email, mot_de_passe_hash, credits,
  role_chauffeur, role_passager, role_interne,
  photo_path, statut, date_changement_statut
)
VALUES
/* Admin */
('jose',     'jose@ecoride.fr',
 '$2y$10$2yW4Acq9GFz6Y1t9EwL56nGisiWgNZq6ITZM5jtgUe52RvEJgwBuN',
 20,
 false, false, true,
 'images/img_jose.jpg', 'ACTIF', NULL),

/* Employés */
('sophie',   'sophie@ecoride.fr',
 '$2y$10$O6n9JEC3HqdZ6J6afU1zT0YQOaNF03vpUuT3em6KopZ9jZICffu4G',
 20,
 false, false, true,
 'images/img_sophie.jpg', 'ACTIF', NULL),

('thomas',   'thomas@ecoride.fr',
 '$2y$10$2yW4Acq9GFz6Y1t9EwL56nGisiWgNZq6ITZM5jtgUe52RvEJgwBuN',
 20,
 false, false, true,
 'images/img_thomas.jpg', 'SUSPENDU', '2026-12-16 10:00:00'),

/* Utilisateurs "métier" */
('muriel',   'muriel@ecoride.fr',
 '$2y$10$7FgtJsThJv07In9ZMJLsCfMZyuKpslm0lcNQqEefRWi4j7c1f5S71',
 20,
 true,  true,  false,
 'images/img_muriel.jpg', 'ACTIF', NULL),

('benjamin', 'benjamin@ecoride.fr',
 '$2y$10$IRz1THrHZp2n5RL08ALrCFQPS6YwfuNhFLOv2mpbUrhToxYkvB0dg',
 20,
 true,  false, false,
 'images/img_benji.jpg', 'ACTIF', NULL),

('raoul',    'raoul@ecoride.fr',
 '$2y$10$Yj2Soc0KO67IMRebhOmM1Khzfx1hcMbm9lThEnUZd7RbIBNg1qeoe',
 50,
 false, true,  false,
 'images/img_raoul.jpg', 'ACTIF', NULL),

('nina',     'nina@ecoride.fr',
 '$2y$10$Yj2Soc0KO67IMRebhOmM1Khzfx1hcMbm9lThEnUZd7RbIBNg1qeoe',
 50,
 false, true,  false,
 'images/img_nina.jpg', 'ACTIF', NULL),

('luc',      'luc@ecoride.fr',
 '$2y$10$IRz1THrHZp2n5RL08ALrCFQPS6YwfuNhFLOv2mpbUrhToxYkvB0dg',
 50,
 true,  true,  false,
 'images/img_luc.jpg', 'ACTIF', NULL),

('emma',     'emma@ecoride.fr',
 '$2y$10$O6n9JEC3HqdZ6J6afU1zT0YQOaNF03vpUuT3em6KopZ9jZICffu4G',
 20,
 false, true,  false,
 'images/img_emma.jpg', 'SUSPENDU', '2026-12-15 09:00:00');

/*
   2) RÔLES SPÉCIALISÉS
   - triggers AFTER INSERT mettent role_interne=true (déjà true par sécurité)
 */

INSERT INTO administrateur (id_utilisateur)
SELECT id_utilisateur FROM utilisateur WHERE pseudo = 'jose';

INSERT INTO employe (id_utilisateur)
SELECT id_utilisateur FROM utilisateur WHERE pseudo = 'sophie';

INSERT INTO employe (id_utilisateur)
SELECT id_utilisateur FROM utilisateur WHERE pseudo = 'thomas';

/*
   3) VOITURES
  */

INSERT INTO voiture (
  est_active, date_desactivation,
  immatriculation, date_1ere_mise_en_circulation,
  marque, couleur, energie, nb_places,
  id_utilisateur
)
VALUES
(true, NULL, 'AA123BB', '2021-03-10', 'TESLA',   'NOIR',  'ELECTRIQUE', 3,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='muriel')),

(true, NULL, 'CC456DD', '2016-06-22', 'RENAULT', 'BLEU',  'DIESEL',     2,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='benjamin')),

(true, NULL, 'EE789FF', '2019-11-05', 'PEUGEOT', 'GRIS',  'HYBRIDE',    3,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='luc'));

/*
   4) COVOITURAGES
   - On couvre plusieurs statuts
   - IMPORTANT : on ne réservera PAS sur ANNULE / INCIDENT / TERMINE (règle trigger)
   - On ajoute un trajet "TERMINE" avec participations pour déclencher EN_ATTENTE + validations.
*/

INSERT INTO covoiturage (
  date_heure_depart, date_heure_arrivee,
  adresse_depart, adresse_arrivee, ville_depart, ville_arrivee,
  latitude_depart, longitude_depart, latitude_arrivee, longitude_arrivee,
  nb_places_dispo, prix_credits, commission_credits,
  statut_covoiturage, incident_commentaire, incident_resolu,

  est_non_fumeur, accepte_animaux, preferences_libre,

  id_utilisateur, id_voiture
)

VALUES
/* A) PLANIFIE électrique (Muriel) */
(
  '2027-01-01 08:00:00', '2027-01-01 10:20:00',
  'Gare Annemasse', 'Gare Lyon Part-Dieu', 'Annemasse', 'Lyon',
  NULL, NULL, NULL, NULL,
  2, 12, 2,
  'PLANIFIE', NULL, false,

  true, false, 'Bagages acceptés si raisonnables.',

  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='muriel'),
  (SELECT id_voiture FROM voiture WHERE immatriculation='AA123BB')
),

/* B) PLANIFIE diesel (Benjamin) */
(
  '2027-01-01 18:30:00', '2027-01-01 20:45:00',
  'Centre Annemasse', 'Lyon Perrache', 'Annemasse', 'Lyon',
  NULL, NULL, NULL, NULL,
  1, 8, 2,
  'PLANIFIE', NULL, false,

  false, true, 'Petit animal en cage accepté.',

  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='benjamin'),
  (SELECT id_voiture FROM voiture WHERE immatriculation='CC456DD')
),

/* C) EN_COURS (Muriel) */
(
  '2027-01-02 07:30:00', '2027-01-02 09:50:00',
  'Gare Annemasse', 'Gare Lyon Part-Dieu', 'Annemasse', 'Lyon',
  NULL, NULL, NULL, NULL,
  1, 10, 2,
  'EN_COURS', NULL, false,

  true, false, NULL,

  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='muriel'),
  (SELECT id_voiture FROM voiture WHERE immatriculation='AA123BB')
),

/* D) ANNULE (Muriel) */
(
  '2027-01-04 08:15:00', '2027-01-04 10:40:00',
  'Gare Annemasse', 'Lyon Perrache', 'Annemasse', 'Lyon',
  NULL, NULL, NULL, NULL,
  2, 9, 2,
  'ANNULE', NULL, false,

  true, false, 'Annulé : imprévu de dernière minute.',

  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='muriel'),
  (SELECT id_voiture FROM voiture WHERE immatriculation='AA123BB')
),

/* E) INCIDENT (Benjamin) */
(
  '2027-01-05 18:10:00', '2027-01-05 20:35:00',
  'Annemasse', 'Lyon Part-Dieu', 'Annemasse', 'Lyon',
  NULL, NULL, NULL, NULL,
  2, 10, 2,
  'INCIDENT', 'Retard important et désaccord sur le point de prise en charge.', false,

  false, false, 'Communication à améliorer sur le point de rendez-vous.',

  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='benjamin'),
  (SELECT id_voiture FROM voiture WHERE immatriculation='CC456DD')
),

/* F) PLANIFIE dédié aux avis EN_ATTENTE / REFUSE (Benjamin) */
(
  '2027-01-06 17:30:00', '2027-01-06 19:55:00',
  'Annemasse Centre', 'Gare Lyon Part-Dieu', 'Annemasse', 'Lyon',
  NULL, NULL, NULL, NULL,
  2, 11, 2,
  'PLANIFIE', NULL, false,

  true, false, 'Merci d’être à l’heure, départ pile.',

  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='benjamin'),
  (SELECT id_voiture FROM voiture WHERE immatriculation='CC456DD')
),

/* G) PLANIFIE -> TERMINE ensuite, avec participations + validations (Muriel) */
(
  '2027-01-07 07:40:00', '2027-01-07 10:00:00',
  'Gare Annemasse', 'Gare Lyon Part-Dieu', 'Annemasse', 'Lyon',
  NULL, NULL, NULL, NULL,
  2, 12, 2,
  'PLANIFIE', NULL, false,

  true, true, 'Musique douce ok, pas de nourriture odorante.',

  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='muriel'),
  (SELECT id_voiture FROM voiture WHERE immatriculation='AA123BB')
);

/*
   5) PARTICIPATIONS
   - Le trigger AFTER INSERT/UPDATE/DELETE gère nb_places_dispo et credits passager
   - On évite de réserver sur ANNULE / INCIDENT (interdit)
   - On inclut une annulation pour tester remboursement + place rendue
 */

INSERT INTO participation (
  date_heure_confirmation, credits_utilises, est_annulee,
  statut_validation, commentaire_validation,
  id_utilisateur, id_covoiturage
)
VALUES
/* Raoul sur A */
(
  '2026-12-20 19:00:00',
  12,
  false,
  'NON_DEMANDEE', NULL,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='raoul'),
  (SELECT id_covoiturage FROM covoiturage WHERE date_heure_depart='2027-01-01 08:00:00')
),

/* Nina sur A */
(
  '2026-12-22 10:00:00',
  12,
  false,
  'NON_DEMANDEE', NULL,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='nina'),
  (SELECT id_covoiturage FROM covoiturage WHERE date_heure_depart='2027-01-01 08:00:00')
),

/* Nina annule sur B (doit rembourser + rendre la place) */
(
  '2026-12-21 20:00:00',
  8,
  true,
  'NON_DEMANDEE', NULL,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='nina'),
  (SELECT id_covoiturage FROM covoiturage WHERE date_heure_depart='2027-01-01 18:30:00')
),

/* Luc sur F (avis EN_ATTENTE ensuite) */
(
  '2026-12-22 18:00:00',
  11,
  false,
  'NON_DEMANDEE', NULL,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='luc'),
  (SELECT id_covoiturage FROM covoiturage WHERE date_heure_depart='2027-01-06 17:30:00')
),

/* Raoul sur F (avis REFUSE ensuite) */
(
  '2026-12-22 18:10:00',
  11,
  false,
  'NON_DEMANDEE', NULL,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='raoul'),
  (SELECT id_covoiturage FROM covoiturage WHERE date_heure_depart='2027-01-06 17:30:00')
),

/* Raoul sur G (sera validé OK après TERMINE) */
(
  '2026-12-28 09:30:00',
  12,
  false,
  'NON_DEMANDEE', NULL,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='raoul'),
  (SELECT id_covoiturage FROM covoiturage WHERE date_heure_depart='2027-01-07 07:40:00')
),

/* Nina sur G (sera validé PROBLEME après TERMINE) */
(
  '2026-12-28 09:40:00',
  12,
  false,
  'NON_DEMANDEE', NULL,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='nina'),
  (SELECT id_covoiturage FROM covoiturage WHERE date_heure_depart='2027-01-07 07:40:00')
);

/*
   6) AVIS
   - 1 avis par participation (contrainte uq_avis_participation)
   - id_employe_moderateur doit référencer employe(id_utilisateur) ou NULL
 */

INSERT INTO avis (
  note, commentaire, date_depot,
  statut_moderation, id_participation, id_employe_moderateur
)
VALUES
/* VALIDE pour Raoul sur A, modéré par Sophie */
(
  5,
  'Trajet fluide, conduite agréable.',
  '2027-01-01 11:00:00',
  'VALIDE',
  (SELECT part.id_participation
   FROM participation part
   JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
   JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
   WHERE util.pseudo = 'raoul'
     AND covo.date_heure_depart = '2027-01-01 08:00:00'
  ),
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='sophie')
),

/* VALIDE pour Nina sur A, modéré par Sophie */
(
  5,
  'Très bon trajet, ponctuel et confortable.',
  '2027-01-01 11:10:00',
  'VALIDE',
  (SELECT part.id_participation
   FROM participation part
   JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
   JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
   WHERE util.pseudo = 'nina'
     AND covo.date_heure_depart = '2027-01-01 08:00:00'
  ),
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='sophie')
),

/* EN_ATTENTE pour Luc sur F, pas encore modéré */
(
  4,
  'Bon trajet, mais un peu de retard.',
  '2027-01-06 20:10:00',
  'EN_ATTENTE',
  (SELECT part.id_participation
   FROM participation part
   JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
   JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
   WHERE util.pseudo = 'luc'
     AND covo.date_heure_depart = '2027-01-06 17:30:00'
  ),
  NULL
),

/* REFUSE pour Raoul sur F, modéré par Thomas (même suspendu, il reste employé en BD) */
(
  1,
  'Commentaire agressif sans détail utile.',
  '2027-01-06 20:20:00',
  'REFUSE',
  (SELECT part.id_participation
   FROM participation part
   JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
   JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
   WHERE util.pseudo = 'raoul'
     AND covo.date_heure_depart = '2027-01-06 17:30:00'
  ),
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='thomas')
);

/*
   7) SCÉNARIO "VALIDATION" POUR TESTER LES TRIGGERS
   - Étape 1 : le covoiturage G passe à TERMINE -> participations non annulées => EN_ATTENTE
   - Étape 2 : Raoul valide OK -> crédite chauffeur + insère commission_plateforme
   - Étape 3 : Nina signale PROBLEME -> covoiturage passe INCIDENT + commentaire copié
*/

/* Étape 1 : passage à TERMINE => participations sur G passent EN_ATTENTE */
UPDATE covoiturage
SET statut_covoiturage = 'TERMINE'
WHERE date_heure_depart = '2027-01-07 07:40:00';

/* Étape 2 : validation OK (Raoul) => gain chauffeur + commission */
UPDATE participation
SET statut_validation = 'OK'
WHERE id_participation = (
  SELECT part.id_participation
  FROM participation part
  JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
  JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
  WHERE util.pseudo = 'raoul'
    AND covo.date_heure_depart = '2027-01-07 07:40:00'
);

/* Étape 3 : validation PROBLEME (Nina) => covoiturage INCIDENT */
UPDATE participation
SET statut_validation = 'PROBLEME',
    commentaire_validation = 'Retard + changement de point de rendez-vous non annoncé.'
WHERE id_participation = (
  SELECT part.id_participation
  FROM participation part
  JOIN utilisateur util ON util.id_utilisateur = part.id_utilisateur
  JOIN covoiturage covo ON covo.id_covoiturage = part.id_covoiturage
  WHERE util.pseudo = 'nina'
    AND covo.date_heure_depart = '2027-01-07 07:40:00'
);

COMMIT;
