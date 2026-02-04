BEGIN;
/*
-- Script d'insertion de données de démonstration BDD EcoRide
-- Script rejouable
*/
TRUNCATE TABLE
  avis,
  participation,
  covoiturage,
  voiture,
  employe,
  administrateur,
  utilisateur
RESTART IDENTITY CASCADE;

/*1. UTILISATEURS
-- mot_de_passe_hash > contrainte de longueur mdp = 60 > hash bcrypt simulé
*/
INSERT INTO utilisateur (pseudo, email, mot_de_passe_hash, credits, role_chauffeur, role_passager, photo_path, statut)
VALUES
('jose',     'jose@ecoride.fr',     '$2y$10$2yW4Acq9GFz6Y1t9EwL56nGisiWgNZq6ITZM5jtgUe52RvEJgwBuN', 20, false, true,  NULL, 'ACTIF'),
('sophie',   'sophie@ecoride.fr',   '$2y$10$O6n9JEC3HqdZ6J6afU1zT0YQOaNF03vpUuT3em6KopZ9jZICffu4G', 20, false, true,  NULL, 'ACTIF'),
('muriel',   'muriel@ecoride.fr',   '$2y$10$7FgtJsThJv07In9ZMJLsCfMZyuKpslm0lcNQqEefRWi4j7c1f5S71', 20, true,  true,  'photos/muriel.jpg', 'ACTIF'),
('benjamin', 'benjamin@ecoride.fr', '$2y$10$IRz1THrHZp2n5RL08ALrCFQPS6YwfuNhFLOv2mpbUrhToxYkvB0dg', 20, true,  false, NULL, 'ACTIF'),
('raoul',    'raoul@ecoride.fr',    '$2y$10$Yj2Soc0KO67IMRebhOmM1Khzfx1hcMbm9lThEnUZd7RbIBNg1qeoe', 20, false, true,  NULL, 'ACTIF');

/*
--2. ROLES UTILISATEUR > ADMINISTRATEUR > EMPLOYE
*/
INSERT INTO administrateur (id_utilisateur)
SELECT id_utilisateur FROM utilisateur WHERE pseudo = 'jose';

INSERT INTO employe (id_utilisateur)
SELECT id_utilisateur FROM utilisateur WHERE pseudo = 'sophie';
/*
--3. VOITURES > Muriel électrique > Benjamin diesel
*/
INSERT INTO voiture (
  est_active, date_desactivation,
  immatriculation, date_1ere_mise_en_circulation,
  marque, couleur, energie, nb_places,
  id_utilisateur
)
VALUES
(true, NULL, 'AA123BB', '2021-03-10', 'TESLA',   'NOIR',  'ELECTRIQUE', 3, (SELECT id_utilisateur FROM utilisateur WHERE pseudo='muriel')),
(true, NULL, 'CC456DD', '2016-06-22', 'RENAULT', 'BLEU',  'DIESEL',     2, (SELECT id_utilisateur FROM utilisateur WHERE pseudo='benjamin'));
/*
--4. COVOITURAGES > cohérence des dates > statut Planifié
*/
INSERT INTO covoiturage (
  date_heure_depart, date_heure_arrivee,
  adresse_depart, adresse_arrivee, ville_depart, ville_arrivee,
  latitude_depart, longitude_depart, latitude_arrivee, longitude_arrivee,
  nb_places_dispo, prix_credits, commission_credits,
  statut_covoiturage, incident_commentaire, incident_resolu,
  id_utilisateur, id_voiture
)
VALUES
(
  '2026-02-05 08:00:00', '2026-02-05 09:10:00',
  'Gare Annemasse', 'Gare Genève', 'Annemasse', 'Genève',
  NULL, NULL, NULL, NULL,
  2, 12, 2,
  'PLANIFIE', NULL, false,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='muriel'),
  (SELECT id_voiture FROM voiture WHERE immatriculation='AA123BB')
),
(
  '2026-02-06 18:30:00', '2026-02-06 19:20:00',
  'Centre Annemasse', 'Cornavin', 'Annemasse', 'Genève',
  NULL, NULL, NULL, NULL,
  1, 8, 2,
  'PLANIFIE', NULL, false,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='benjamin'),
  (SELECT id_voiture FROM voiture WHERE immatriculation='CC456DD')
);
/*
--5. PARTICIPATIONS > Raoul réserve le covoiturage de Muriel
*/
INSERT INTO participation (date_heure_confirmation, credits_utilises, est_annulee, id_utilisateur, id_covoiturage)
VALUES
(
  '2026-01-28 19:00:00',
  12,
  false,
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='raoul'),
  (SELECT id_covoiturage FROM covoiturage
   WHERE date_heure_depart='2026-02-05 08:00:00'
     AND ville_depart='Annemasse'
     AND ville_arrivee='Genève')
);
/*
--6. AVIS > Raoul laisse un avis > modéré par Sophie
*/
INSERT INTO avis (note, commentaire, date_depot, statut_moderation, id_participation, id_employe_moderateur)
VALUES
(
  5,
  'Trajet fluide, conduite agréable.',
  '2026-02-05 10:30:00',
  'VALIDE',
  (SELECT id_participation FROM participation
   WHERE id_utilisateur = (SELECT id_utilisateur FROM utilisateur WHERE pseudo='raoul')
     AND id_covoiturage = (SELECT id_covoiturage FROM covoiturage WHERE date_heure_depart='2026-02-05 08:00:00')
  ),
  (SELECT id_utilisateur FROM utilisateur WHERE pseudo='sophie')
);

COMMIT;
