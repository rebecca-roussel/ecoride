BEGIN;

CREATE TABLE utilisateur (
id_utilisateur INTEGER GENERATED ALWAYS AS IDENTITY,
pseudo VARCHAR(50) NOT NULL,
email VARCHAR(255) NOT NULL,
mot_de_passe_hash VARCHAR(255) NOT NULL,
credits INTEGER NOT NULL,
role_chauffeur BOOLEAN NOT NULL,
role_passager BOOLEAN NOT NULL,
photo_path VARCHAR(255),
statut VARCHAR(20) NOT NULL,
PRIMARY KEY (id_utilisateur),
UNIQUE (pseudo),
UNIQUE (email)
);

CREATE TABLE voiture (
id_voiture INTEGER GENERATED ALWAYS AS IDENTITY,
immatriculation VARCHAR(15) NOT NULL,
date_1ere_mise_en_circulation DATE NOT NULL,
marque VARCHAR(50) NOT NULL,
couleur VARCHAR(30) NOT NULL,
energie VARCHAR(20) NOT NULL,
nb_places INTEGER NOT NULL,
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
adresse_arrivee VARCHAR(255) NOT NULL,
adresse_depart VARCHAR(255) NOT NULL,
ville_depart VARCHAR(80) NOT NULL,
ville_arrivee VARCHAR(80) NOT NULL,
nb_places_dispo INTEGER NOT NULL,
prix_credits INTEGER NOT NULL,
commission_credits INTEGER NOT NULL,
statut_covoiturage VARCHAR(20) NOT NULL,
incident_commentaire VARCHAR(1000),
incident_resolu BOOLEAN NOT NULL,
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
est_annulee BOOLEAN NOT NULL,
id_utilisateur INTEGER NOT NULL,
id_covoiturage INTEGER NOT NULL,
PRIMARY KEY (id_participation),
CONSTRAINT fk_participation_utilisateur
FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur),
CONSTRAINT fk_participation_covoiturage
FOREIGN KEY (id_covoiturage) REFERENCES covoiturage(id_covoiturage)
);

CREATE TABLE employe (
id_utilisateur INTEGER,
PRIMARY KEY (id_utilisateur),
CONSTRAINT fk_employe_utilisateur
FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);

CREATE TABLE administrateur (
id_utilisateur INTEGER,
PRIMARY KEY (id_utilisateur),
CONSTRAINT fk_administrateur_utilisateur
FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);

CREATE TABLE avis (
id_avis INTEGER GENERATED ALWAYS AS IDENTITY,
note INTEGER NOT NULL,
commentaire VARCHAR(1000),
date_depot TIMESTAMP NOT NULL,
statut_moderation VARCHAR(20) NOT NULL,
id_participation INTEGER NOT NULL,
id_utilisateur INTEGER,
PRIMARY KEY (id_avis),
UNIQUE (id_participation),
CONSTRAINT fk_avis_participation
FOREIGN KEY (id_participation) REFERENCES participation(id_participation),
CONSTRAINT fk_avis_employe
FOREIGN KEY (id_utilisateur) REFERENCES employe(id_utilisateur)
);

COMMIT;