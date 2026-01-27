BEGIN;

CREATE TABLE utilisateur (
  id_utilisateur INTEGER GENERATED ALWAYS AS IDENTITY,
 

  pseudo VARCHAR(50) NOT NULL,
 -- empêcher un pseudo vide
  CONSTRAINT ck_utilisateur_pseudo_non_vide CHECK (length(trim(pseudo)) > 0),

  email VARCHAR(255) NOT NULL,
-- empêcher un email vide
CONSTRAINT ck_utilisateur_email_non_vide CHECK (length(trim(email)) > 0),

  mot_de_passe_hash VARCHAR(255) NOT NULL,
-- la valeur stockée dans la base doit ressembler à un hash bcrypt, sinon refus d’enregistrement 
  CONSTRAINT ck_mdp_bcrypt_longueur CHECK (length(mot_de_passe_hash) = 60),

/* crédits donnés à l'inscription = 20 */
  credits INTEGER NOT NULL DEFAULT 20,
-- sécurisation : interdit les crédits négatifs 
  CONSTRAINT ck_utilisateur_credits_nonneg CHECK (credits >= 0),

/* l'utilisateur choisit son rôle à l'inscription, il peut choisir les deux*/
  role_chauffeur BOOLEAN NOT NULL DEFAULT FALSE,
  role_passager BOOLEAN NOT NULL DEFAULT FALSE,
  -- Au moins un rôle est sélectionné à l'inscription
CONSTRAINT ck_utilisateur_au_moins_un_role CHECK (role_chauffeur OR role_passager),

  photo_path VARCHAR(255),

  /* liste fermée pilotée par l'admin */
 statut VARCHAR(20) NOT NULL DEFAULT 'ACTIF',
  CONSTRAINT ck_utilisateur_statut CHECK (
    statut IN ('ACTIF','SUSPENDU')
  ),

  date_changement_statut TIMESTAMP NULL,

  PRIMARY KEY (id_utilisateur),
  UNIQUE (pseudo),
  UNIQUE (email)
);


CREATE TABLE voiture (
id_voiture INTEGER GENERATED ALWAYS AS IDENTITY,

/* Ajout du statut de la voiture */
est_active BOOLEAN NOT NULL DEFAULT true,
date_desactivation TIMESTAMP NULL,
-- donne une date de desactivation voiture
CONSTRAINT ck_voiture_desactivation CHECK (
  (est_active = true  AND date_desactivation IS NULL)
  OR
  (est_active = false AND date_desactivation IS NOT NULL)
),

/* l’immatriculation est unique afin de simplifier et éviter les doublons, en acceptant la limite théorique d’une réattribution future de plaque.*/
immatriculation VARCHAR(15) NOT NULL,
-- plaque d'immatriculation en majuscule, sans espaces, sans caractères spéciaux obligatoire
CONSTRAINT ck_voiture_immat_format CHECK (
  immatriculation = upper(immatriculation)
  AND immatriculation ~ '^[A-Z0-9]+$'
),

date_1ere_mise_en_circulation DATE NOT NULL,

marque VARCHAR(50) NOT NULL,
couleur VARCHAR(30) NOT NULL,
-- empêche un champs vide
CONSTRAINT ck_voiture_marque_non_vide CHECK (length(trim(marque)) > 0),
CONSTRAINT ck_voiture_couleur_non_vide CHECK (length(trim(couleur)) > 0),

energie VARCHAR(20) NOT NULL,
-- liste déroulante du choix d'énergie pour la voiture
CONSTRAINT ck_voiture_energie CHECK (energie IN ('ESSENCE','DIESEL','ETHANOL','HYBRIDE','ELECTRIQUE')),

nb_places INTEGER NOT NULL,
-- nb_places correspond au nombre maximum de passagers possibles (sans le siège conducteur donc minimum 1 place et max 4)
CONSTRAINT ck_voiture_nb_places CHECK (nb_places BETWEEN 1 AND 4),

id_utilisateur INTEGER NOT NULL,
PRIMARY KEY (id_voiture),
UNIQUE (immatriculation),
CONSTRAINT fk_voiture_utilisateur
FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);

CREATE TABLE covoiturage (
id_covoiturage INTEGER GENERATED ALWAYS AS IDENTITY,

date_heure_depart TIMESTAMP NOT NULL,
date_heure_arrivee TIMESTAMP NOT NULL,
-- éviter les incohérences temporelles :
CONSTRAINT ck_covoiturage_dates_coherentes
CHECK (date_heure_arrivee > date_heure_depart),

adresse_arrivee VARCHAR(255) NOT NULL,
adresse_depart VARCHAR(255) NOT NULL,
ville_depart VARCHAR(80) NOT NULL,
ville_arrivee VARCHAR(80) NOT NULL,
-- Empêche des champs "vides" composés uniquement d'espaces
CONSTRAINT ck_adresse_depart_non_vide  CHECK (length(trim(adresse_depart))  > 0),
CONSTRAINT ck_adresse_arrivee_non_vide CHECK (length(trim(adresse_arrivee)) > 0),
CONSTRAINT ck_ville_depart_non_vide    CHECK (length(trim(ville_depart))    > 0),
CONSTRAINT ck_ville_arrivee_non_vide   CHECK (length(trim(ville_arrivee))   > 0),

/* option de géolocalisation */
latitude_depart  NUMERIC(9,6),
longitude_depart NUMERIC(9,6),
latitude_arrivee  NUMERIC(9,6),
longitude_arrivee NUMERIC(9,6),
-- empêche d’enregistrer des coordonnées qui n’existent pas sur terre et pas de latitude sans longitude (et inversement)
CONSTRAINT ck_lat_depart CHECK (latitude_depart IS NULL OR latitude_depart BETWEEN -90 AND 90),
CONSTRAINT ck_lon_depart CHECK (longitude_depart IS NULL OR longitude_depart BETWEEN -180 AND 180),
CONSTRAINT ck_lat_arrivee CHECK (latitude_arrivee IS NULL OR latitude_arrivee BETWEEN -90 AND 90),
CONSTRAINT ck_lon_arrivee CHECK (longitude_arrivee IS NULL OR longitude_arrivee BETWEEN -180 AND 180),
CONSTRAINT ck_geo_depart_pair CHECK (
  (latitude_depart IS NULL AND longitude_depart IS NULL)
  OR (latitude_depart IS NOT NULL AND longitude_depart IS NOT NULL)
),
CONSTRAINT ck_geo_arrivee_pair CHECK (
  (latitude_arrivee IS NULL AND longitude_arrivee IS NULL)
  OR (latitude_arrivee IS NOT NULL AND longitude_arrivee IS NOT NULL)
),


nb_places_dispo INTEGER NOT NULL,
-- ne peut être <1 et max 4 places sinon incohérences
CONSTRAINT ck_covoiturage_nb_places_dispo CHECK (nb_places_dispo BETWEEN 1 AND 4),

prix_credits INTEGER NOT NULL,
-- empêche un covoiturage gratuit ou négatif, ce qui serait incohérent économiquement
CONSTRAINT ck_covoiturage_prix_credits CHECK (prix_credits > 0),

commission_credits INTEGER NOT NULL DEFAULT 2,
-- commission plateforme fixée à 2 crédits
CONSTRAINT ck_covoiturage_commission_fixe CHECK (commission_credits = 2),

statut_covoiturage VARCHAR(20) NOT NULL,
-- liste fermée du status du covoiturage
CONSTRAINT ck_covoiturage_statut CHECK (
  statut_covoiturage IN ('PLANIFIE','EN_COURS','TERMINE','ANNULE','INCIDENT')
),

/* champ d'incident volontairement restreint pour traitement rapide par l'employé */
incident_commentaire VARCHAR(1000),
incident_resolu BOOLEAN NOT NULL DEFAULT false,
-- commentaire d'incident rendu obligatoire si statut_covoiturage = INCIDENT
CONSTRAINT ck_incident_commentaire_obligatoire CHECK (
  (statut_covoiturage <> 'INCIDENT' AND incident_commentaire IS NULL AND incident_resolu = false)
  OR
  (statut_covoiturage = 'INCIDENT' AND length(trim(incident_commentaire)) > 0)
),

id_utilisateur INTEGER NOT NULL,
id_voiture INTEGER NOT NULL,

PRIMARY KEY (id_covoiturage),
CONSTRAINT fk_covoiturage_utilisateur
FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur),
CONSTRAINT fk_covoiturage_voiture
FOREIGN KEY (id_voiture) REFERENCES voiture(id_voiture)
);

CREATE TABLE participation (
id_participation INTEGER GENERATED ALWAYS AS IDENTITY,
date_heure_confirmation TIMESTAMP NOT NULL,

credits_utilises INTEGER NOT NULL,
-- empêche des crédits absurdes
CONSTRAINT ck_participation_credits_utilises CHECK (credits_utilises > 0),

est_annulee BOOLEAN NOT NULL DEFAULT false,

id_utilisateur INTEGER NOT NULL,
id_covoiturage INTEGER NOT NULL,
PRIMARY KEY (id_participation),
  -- empêche qu'un même utilisateur réserve plusieurs fois un covoiturage
  CONSTRAINT uq_participation UNIQUE (id_utilisateur, id_covoiturage),
CONSTRAINT fk_participation_utilisateur
FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur),
CONSTRAINT fk_participation_covoiturage
FOREIGN KEY (id_covoiturage) REFERENCES covoiturage(id_covoiturage)
);

CREATE TABLE employe (
id_utilisateur INTEGER NOT NULL,
PRIMARY KEY (id_utilisateur),
CONSTRAINT fk_employe_utilisateur
FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);

CREATE TABLE administrateur (
id_utilisateur INTEGER NOT NULL,
PRIMARY KEY (id_utilisateur),
CONSTRAINT fk_administrateur_utilisateur
FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);

CREATE TABLE avis (
  id_avis INTEGER GENERATED ALWAYS AS IDENTITY,

 
  note INTEGER NOT NULL,
   -- Définis la note donnée par le passager (de 1 à 5)
  CONSTRAINT ck_avis_note CHECK (note BETWEEN 1 AND 5),

  /*Permet au passager d’ajouter un commentaire libre s'il le souhaite*/
  commentaire VARCHAR(1000),

  date_depot TIMESTAMP NOT NULL,

  
  statut_moderation VARCHAR(20) NOT NULL,
  -- liste indiquant l’état de modération de l’avis
  CONSTRAINT ck_avis_statut CHECK (
    statut_moderation IN ('EN_ATTENTE','VALIDE','REFUSE')
  ),

  
  id_participation INTEGER NOT NULL,
  -- Lie l’avis à une participation unique
  CONSTRAINT uq_avis_participation UNIQUE (id_participation),
  CONSTRAINT fk_avis_participation
    FOREIGN KEY (id_participation) REFERENCES participation(id_participation),

  
  id_employe_moderateur INTEGER NULL,
  -- Identifie l’employé ayant modéré l’avis (si modération effectuée)
  CONSTRAINT fk_avis_employe
    FOREIGN KEY (id_employe_moderateur) REFERENCES employe(id_utilisateur),

  PRIMARY KEY (id_avis)
);

COMMIT;