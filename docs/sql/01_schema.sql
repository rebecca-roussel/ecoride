BEGIN;

/* Remise à zéro
   Je repars sur un schéma propre pour éviter les tables déjà existantes */
--DROP SCHEMA IF EXISTS public CASCADE;
--CREATE SCHEMA public;
--SET search_path TO public;

/*
  Table utilisateur
  Objectif
  - stocker les comptes
  - garantir pseudo et email uniques
  - rôles chauffeur / passager en colonnes
  - rôle interne (employé/admin) en colonne pour cohérence BDD
*/
CREATE TABLE utilisateur (
  id_utilisateur INTEGER GENERATED ALWAYS AS IDENTITY,

  pseudo VARCHAR(50) NOT NULL,
  CONSTRAINT ck_utilisateur_pseudo_non_vide CHECK (length(trim(pseudo)) > 0),

  email VARCHAR(255) NOT NULL,
  CONSTRAINT ck_utilisateur_email_non_vide CHECK (length(trim(email)) > 0),

  mot_de_passe_hash VARCHAR(255) NOT NULL,
  CONSTRAINT ck_mdp_bcrypt_longueur CHECK (length(mot_de_passe_hash) = 60),

  credits INTEGER NOT NULL DEFAULT 20,
  CONSTRAINT ck_utilisateur_credits_nonneg CHECK (credits >= 0),

  role_chauffeur BOOLEAN NOT NULL DEFAULT false,
  role_passager BOOLEAN NOT NULL DEFAULT false,

  /* Ajout : rôle interne pour cohérence BDD (employé/admin) */
  role_interne  BOOLEAN NOT NULL DEFAULT false,

  /* Ajout : au moins un rôle */
  CONSTRAINT ck_utilisateur_au_moins_un_role
  CHECK (role_chauffeur OR role_passager OR role_interne),

  photo_path VARCHAR(255),

  statut VARCHAR(20) NOT NULL DEFAULT 'ACTIF',
  CONSTRAINT ck_utilisateur_statut CHECK (statut IN ('ACTIF','SUSPENDU')),

  date_changement_statut TIMESTAMP NULL,

  PRIMARY KEY (id_utilisateur),
  UNIQUE (pseudo),
  UNIQUE (email)
);

/* Table mot de passe 
*/

-- Table technique : réinitialisation de mot de passe (jetons hashés + expiration)
CREATE TABLE reinitialisation_mot_de_passe (
  id_reinitialisation INTEGER GENERATED ALWAYS AS IDENTITY,
  id_utilisateur INTEGER NOT NULL,

  -- On stocke le hash (sha256 hex => 64 caractères), jamais le jeton en clair.
  jeton_hash CHAR(64) NOT NULL,

  date_creation TIMESTAMP NOT NULL DEFAULT now(),
  date_expiration TIMESTAMP NOT NULL,
  date_utilisation TIMESTAMP NULL,

  PRIMARY KEY (id_reinitialisation),

  CONSTRAINT fk_reinitialisation_utilisateur
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE,

  CONSTRAINT uq_reinitialisation_jeton_hash UNIQUE (jeton_hash)
);

CREATE INDEX idx_reinitialisation_utilisateur
  ON reinitialisation_mot_de_passe (id_utilisateur);

CREATE INDEX idx_reinitialisation_expiration
  ON reinitialisation_mot_de_passe (date_expiration);


/*
  Table voiture
*/
CREATE TABLE voiture (
  id_voiture INTEGER GENERATED ALWAYS AS IDENTITY,

  est_active BOOLEAN NOT NULL DEFAULT true,
  date_desactivation TIMESTAMP NULL,
  CONSTRAINT ck_voiture_desactivation CHECK (
    (est_active = true  AND date_desactivation IS NULL)
    OR
    (est_active = false AND date_desactivation IS NOT NULL)
  ),

  immatriculation VARCHAR(15) NOT NULL,
  CONSTRAINT ck_voiture_immat_format CHECK (
    immatriculation = upper(immatriculation)
    AND immatriculation ~ '^[A-Z0-9]+$'
  ),

  date_1ere_mise_en_circulation DATE NOT NULL,

  marque VARCHAR(50) NOT NULL,
  couleur VARCHAR(30) NOT NULL,
  CONSTRAINT ck_voiture_marque_non_vide CHECK (length(trim(marque)) > 0),
  CONSTRAINT ck_voiture_couleur_non_vide CHECK (length(trim(couleur)) > 0),

  energie VARCHAR(20) NOT NULL,
  CONSTRAINT ck_voiture_energie CHECK (energie IN ('ESSENCE','DIESEL','ETHANOL','HYBRIDE','ELECTRIQUE')),

  nb_places INTEGER NOT NULL,
  CONSTRAINT ck_voiture_nb_places CHECK (nb_places BETWEEN 1 AND 4),

  id_utilisateur INTEGER NOT NULL,

  PRIMARY KEY (id_voiture),
  UNIQUE (immatriculation),

  CONSTRAINT fk_voiture_utilisateur
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);

/*
  Table covoiturage
*/
CREATE TABLE covoiturage (
  id_covoiturage INTEGER GENERATED ALWAYS AS IDENTITY,

  date_heure_depart TIMESTAMP NOT NULL,
  date_heure_arrivee TIMESTAMP NOT NULL,
  CONSTRAINT ck_covoiturage_dates_coherentes CHECK (date_heure_arrivee > date_heure_depart),

  adresse_arrivee VARCHAR(255) NOT NULL,
  adresse_depart VARCHAR(255) NOT NULL,
  ville_depart VARCHAR(80) NOT NULL,
  ville_arrivee VARCHAR(80) NOT NULL,
  CONSTRAINT ck_adresse_depart_non_vide  CHECK (length(trim(adresse_depart))  > 0),
  CONSTRAINT ck_adresse_arrivee_non_vide CHECK (length(trim(adresse_arrivee)) > 0),
  CONSTRAINT ck_ville_depart_non_vide    CHECK (length(trim(ville_depart))    > 0),
  CONSTRAINT ck_ville_arrivee_non_vide   CHECK (length(trim(ville_arrivee))   > 0),

  latitude_depart  NUMERIC(9,6),
  longitude_depart NUMERIC(9,6),
  latitude_arrivee  NUMERIC(9,6),
  longitude_arrivee NUMERIC(9,6),

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
  CONSTRAINT ck_covoiturage_nb_places_dispo CHECK (nb_places_dispo BETWEEN 0 AND 4),

  prix_credits INTEGER NOT NULL,
  CONSTRAINT ck_covoiturage_prix_credits CHECK (prix_credits > 2),

  commission_credits INTEGER NOT NULL DEFAULT 2,
  CONSTRAINT ck_covoiturage_commission_fixe CHECK (commission_credits = 2),

  statut_covoiturage VARCHAR(20) NOT NULL DEFAULT 'PLANIFIE',
  CONSTRAINT ck_covoiturage_statut CHECK (
    statut_covoiturage IN ('PLANIFIE','EN_COURS','TERMINE','ANNULE','INCIDENT')
  ),

  incident_commentaire VARCHAR(1000),
  incident_resolu BOOLEAN NOT NULL DEFAULT false,
  CONSTRAINT ck_incident_commentaire_obligatoire CHECK (
    (statut_covoiturage <> 'INCIDENT' AND incident_commentaire IS NULL AND incident_resolu = false)
    OR
    (statut_covoiturage = 'INCIDENT' AND length(trim(incident_commentaire)) > 0)
  ),

  /* Préférences portées par le covoiturage (par trajet) */
  est_non_fumeur BOOLEAN NOT NULL DEFAULT true,
  accepte_animaux BOOLEAN NOT NULL DEFAULT false,
  preferences_libre VARCHAR(255),
  CONSTRAINT ck_covoiturage_preferences_libre_non_vide
    CHECK (preferences_libre IS NULL OR length(trim(preferences_libre)) > 0),

  id_utilisateur INTEGER NOT NULL,
  id_voiture INTEGER NOT NULL,

  PRIMARY KEY (id_covoiturage),

  CONSTRAINT fk_covoiturage_utilisateur
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur),

  CONSTRAINT fk_covoiturage_voiture
    FOREIGN KEY (id_voiture) REFERENCES voiture(id_voiture)
);

/*
  Table participation
*/
CREATE TABLE participation (
  id_participation INTEGER GENERATED ALWAYS AS IDENTITY,

  date_heure_confirmation TIMESTAMP NOT NULL,

  credits_utilises INTEGER NOT NULL,
  CONSTRAINT ck_participation_credits_utilises CHECK (credits_utilises > 0),

  est_annulee BOOLEAN NOT NULL DEFAULT false,

  statut_validation VARCHAR(20) NOT NULL DEFAULT 'NON_DEMANDEE',
  CONSTRAINT ck_participation_statut_validation CHECK (
    statut_validation IN ('NON_DEMANDEE','EN_ATTENTE','OK','PROBLEME')
  ),

  commentaire_validation VARCHAR(1000),
  CONSTRAINT ck_participation_commentaire_si_probleme CHECK (
    (statut_validation <> 'PROBLEME' AND commentaire_validation IS NULL)
    OR
    (statut_validation = 'PROBLEME' AND length(trim(commentaire_validation)) > 0)
  ),

  id_utilisateur INTEGER NOT NULL,
  id_covoiturage INTEGER NOT NULL,

  PRIMARY KEY (id_participation),

  CONSTRAINT uq_participation UNIQUE (id_utilisateur, id_covoiturage),

  CONSTRAINT fk_participation_utilisateur
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur),

  CONSTRAINT fk_participation_covoiturage
    FOREIGN KEY (id_covoiturage) REFERENCES covoiturage(id_covoiturage)
    ON DELETE CASCADE
);

/*
  Table employe / administrateur
*/
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

/*
  Cohérence role_interne
  - si on ajoute un employé / admin => role_interne = true
*/
CREATE OR REPLACE FUNCTION fonction_employe_force_role_interne()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  UPDATE utilisateur
  SET role_interne = true
  WHERE id_utilisateur = NEW.id_utilisateur;

  RETURN NEW;
END;
$$;

CREATE TRIGGER declencheur_employe_force_role_interne
AFTER INSERT ON employe
FOR EACH ROW
EXECUTE FUNCTION fonction_employe_force_role_interne();

CREATE OR REPLACE FUNCTION fonction_administrateur_force_role_interne()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  UPDATE utilisateur
  SET role_interne = true
  WHERE id_utilisateur = NEW.id_utilisateur;

  RETURN NEW;
END;
$$;

CREATE TRIGGER declencheur_administrateur_force_role_interne
AFTER INSERT ON administrateur
FOR EACH ROW
EXECUTE FUNCTION fonction_administrateur_force_role_interne();

/*
  Table avis
*/
CREATE TABLE avis (
  id_avis INTEGER GENERATED ALWAYS AS IDENTITY,

  note INTEGER NOT NULL,
  CONSTRAINT ck_avis_note CHECK (note BETWEEN 1 AND 5),

  commentaire VARCHAR(1000),

  date_depot TIMESTAMP NOT NULL,

  statut_moderation VARCHAR(20) NOT NULL,
  CONSTRAINT ck_avis_statut CHECK (statut_moderation IN ('EN_ATTENTE','VALIDE','REFUSE')),

  id_participation INTEGER NOT NULL,
  CONSTRAINT uq_avis_participation UNIQUE (id_participation),
  CONSTRAINT fk_avis_participation
    FOREIGN KEY (id_participation) REFERENCES participation(id_participation)
    ON DELETE CASCADE,

  id_employe_moderateur INTEGER NULL,
  CONSTRAINT fk_avis_employe
    FOREIGN KEY (id_employe_moderateur) REFERENCES employe(id_utilisateur),

  PRIMARY KEY (id_avis)
);

/*
  Table commission_plateforme
*/
CREATE TABLE commission_plateforme (
  id_commission INTEGER GENERATED ALWAYS AS IDENTITY,

  date_commission TIMESTAMP NOT NULL DEFAULT now(),

  credits_commission INTEGER NOT NULL,
  CONSTRAINT ck_commission_credits_pos CHECK (credits_commission > 0),

  id_participation INTEGER NOT NULL,
  CONSTRAINT uq_commission_participation UNIQUE (id_participation),

  PRIMARY KEY (id_commission),

  CONSTRAINT fk_commission_participation
    FOREIGN KEY (id_participation) REFERENCES participation(id_participation)
    ON DELETE CASCADE
);

CREATE INDEX idx_commission_date
ON commission_plateforme (date_commission);

CREATE INDEX idx_commission_participation
ON commission_plateforme (id_participation);

/*
  Sécurité participation
  Objectif
  - éviter de changer credits_utilises après création
  - ça simplifie le débit et évite les incohérences
*/
CREATE OR REPLACE FUNCTION fonction_verrouiller_credits_utilises()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  IF TG_OP = 'UPDATE' AND NEW.credits_utilises <> OLD.credits_utilises THEN
    RAISE EXCEPTION 'Modification de credits_utilises interdite.';
  END IF;

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS declencheur_verrouiller_credits_utilises ON participation;

CREATE TRIGGER declencheur_verrouiller_credits_utilises
BEFORE UPDATE OF credits_utilises ON participation
FOR EACH ROW
EXECUTE FUNCTION fonction_verrouiller_credits_utilises();

/*
  Sécurité validation
  Objectif
  - une validation finale ne change plus
  - on évite les changements après coup
*/
CREATE OR REPLACE FUNCTION fonction_verrouiller_validation_finale()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  IF OLD.statut_validation IN ('OK','PROBLEME')
     AND NEW.statut_validation <> OLD.statut_validation THEN
    RAISE EXCEPTION 'Validation finale, modification interdite.';
  END IF;

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS declencheur_verrouiller_validation_finale ON participation;

CREATE TRIGGER declencheur_verrouiller_validation_finale
BEFORE UPDATE OF statut_validation ON participation
FOR EACH ROW
EXECUTE FUNCTION fonction_verrouiller_validation_finale();

/*
  Débit et remboursement
  Objectif
  - à la réservation on retire 1 place et on débite le passager
  - à l’annulation on rend 1 place et on rembourse le passager
  - réservation interdite si statut ANNULE TERMINE INCIDENT
  - cas spécial suppression en cascade, on ne bloque pas
*/
CREATE OR REPLACE FUNCTION fonction_places_et_debit_participation()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
  id_cible_covoiturage INTEGER;
  id_cible_utilisateur INTEGER;

  statut_actuel VARCHAR(20);
  places_actuelles INTEGER;

  credits_actuels INTEGER;
  credits_a_debiter INTEGER;

  variation_places INTEGER;
  variation_credits INTEGER;
BEGIN
  id_cible_covoiturage := COALESCE(NEW.id_covoiturage, OLD.id_covoiturage);
  id_cible_utilisateur := COALESCE(NEW.id_utilisateur, OLD.id_utilisateur);

  IF TG_OP = 'INSERT' THEN
    IF NEW.est_annulee = false THEN
      variation_places := -1;
      variation_credits := -NEW.credits_utilises;
    ELSE
      variation_places := 0;
      variation_credits := 0;
    END IF;

  ELSIF TG_OP = 'DELETE' THEN
    IF OLD.est_annulee = false THEN
      variation_places := +1;
      variation_credits := +OLD.credits_utilises;
    ELSE
      variation_places := 0;
      variation_credits := 0;
    END IF;

  ELSIF TG_OP = 'UPDATE' THEN
    IF OLD.est_annulee = false AND NEW.est_annulee = true THEN
      variation_places := +1;
      variation_credits := +OLD.credits_utilises;

    ELSIF OLD.est_annulee = true AND NEW.est_annulee = false THEN
      variation_places := -1;
      variation_credits := -NEW.credits_utilises;

    ELSE
      variation_places := 0;
      variation_credits := 0;
    END IF;

  ELSE
    RAISE EXCEPTION 'Opération non gérée sur participation.';
  END IF;

  IF variation_places = 0 AND variation_credits = 0 THEN
    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
  END IF;

  SELECT nb_places_dispo, statut_covoiturage
  INTO places_actuelles, statut_actuel
  FROM covoiturage
  WHERE id_covoiturage = id_cible_covoiturage
  FOR UPDATE;

  IF places_actuelles IS NULL THEN
    /* Si le covoiturage a été supprimé en cascade, on ne bloque pas */
    IF TG_OP = 'DELETE' THEN
      RETURN OLD;
    END IF;

    RAISE EXCEPTION 'Covoiturage introuvable.';
  END IF;

  IF variation_places = -1 AND statut_actuel IN ('ANNULE','TERMINE','INCIDENT') THEN
    RAISE EXCEPTION 'Participation interdite pour ce statut de covoiturage.';
  END IF;

  IF variation_places = -1 AND places_actuelles <= 0 THEN
    RAISE EXCEPTION 'Aucune place disponible.';
  END IF;

  SELECT credits
  INTO credits_actuels
  FROM utilisateur
  WHERE id_utilisateur = id_cible_utilisateur
  FOR UPDATE;

  IF credits_actuels IS NULL THEN
    RAISE EXCEPTION 'Utilisateur introuvable.';
  END IF;

  IF variation_credits < 0 THEN
    credits_a_debiter := -variation_credits;
    IF credits_actuels < credits_a_debiter THEN
      RAISE EXCEPTION 'Crédits insuffisants.';
    END IF;
  END IF;

  IF variation_places <> 0 THEN
    UPDATE covoiturage
    SET nb_places_dispo = nb_places_dispo + variation_places
    WHERE id_covoiturage = id_cible_covoiturage;
  END IF;

  IF variation_credits <> 0 THEN
    UPDATE utilisateur
    SET credits = credits + variation_credits
    WHERE id_utilisateur = id_cible_utilisateur;
  END IF;

  RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$;

DROP TRIGGER IF EXISTS declencheur_participation_places_debit ON participation;

CREATE TRIGGER declencheur_participation_places_debit
AFTER INSERT OR UPDATE OR DELETE ON participation
FOR EACH ROW
EXECUTE FUNCTION fonction_places_et_debit_participation();

/*
  Passage en terminé
  Objectif
  - quand le covoiturage passe à TERMINE
  - on met les participations non annulées en EN_ATTENTE
*/
CREATE OR REPLACE FUNCTION fonction_covoiturage_termine_demande_validation()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  IF TG_OP = 'UPDATE'
     AND OLD.statut_covoiturage <> 'TERMINE'
     AND NEW.statut_covoiturage = 'TERMINE' THEN

    UPDATE participation
    SET statut_validation = 'EN_ATTENTE'
    WHERE id_covoiturage = NEW.id_covoiturage
      AND est_annulee = false
      AND statut_validation = 'NON_DEMANDEE';
  END IF;

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS declencheur_covoiturage_demande_validation ON covoiturage;

CREATE TRIGGER declencheur_covoiturage_demande_validation
AFTER UPDATE OF statut_covoiturage ON covoiturage
FOR EACH ROW
EXECUTE FUNCTION fonction_covoiturage_termine_demande_validation();

/*
  Validation du participant
  Objectif
  - si OK, créditer le chauffeur et enregistrer la commission plateforme
  - si PROBLEME, passer le covoiturage en INCIDENT et copier le commentaire
*/
CREATE OR REPLACE FUNCTION fonction_validation_participant()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
  statut_actuel VARCHAR(20);
  id_chauffeur INTEGER;
  prix INTEGER;
  commission INTEGER;
  gain_chauffeur INTEGER;
BEGIN
  IF TG_OP <> 'UPDATE' THEN
    RETURN NEW;
  END IF;

  IF NEW.est_annulee = true THEN
    RETURN NEW;
  END IF;

  IF OLD.statut_validation = NEW.statut_validation THEN
    RETURN NEW;
  END IF;

  SELECT statut_covoiturage, id_utilisateur, prix_credits, commission_credits
  INTO statut_actuel, id_chauffeur, prix, commission
  FROM covoiturage
  WHERE id_covoiturage = NEW.id_covoiturage
  FOR UPDATE;

  IF statut_actuel IS NULL THEN
    RAISE EXCEPTION 'Covoiturage introuvable.';
  END IF;

  IF statut_actuel <> 'TERMINE' AND NEW.statut_validation IN ('OK','PROBLEME') THEN
    RAISE EXCEPTION 'Validation impossible tant que le covoiturage n’est pas terminé.';
  END IF;

  IF NEW.statut_validation = 'OK' THEN
    gain_chauffeur := prix - commission;
    IF gain_chauffeur < 0 THEN
      gain_chauffeur := 0;
    END IF;

    UPDATE utilisateur
    SET credits = credits + gain_chauffeur
    WHERE id_utilisateur = id_chauffeur;

    INSERT INTO commission_plateforme (credits_commission, id_participation)
    VALUES (commission, NEW.id_participation);

  ELSIF NEW.statut_validation = 'PROBLEME' THEN
    UPDATE covoiturage
    SET statut_covoiturage = 'INCIDENT',
        incident_commentaire = NEW.commentaire_validation,
        incident_resolu = false
    WHERE id_covoiturage = NEW.id_covoiturage;
  END IF;

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS declencheur_participation_validation ON participation;

CREATE TRIGGER declencheur_participation_validation
AFTER UPDATE OF statut_validation, commentaire_validation ON participation
FOR EACH ROW
EXECUTE FUNCTION fonction_validation_participant();

/* Index recherche
   Objectif
   - accélérer la recherche ville départ + ville arrivée + date */
CREATE INDEX idx_covoiturage_recherche
ON covoiturage (ville_depart, ville_arrivee, date_heure_depart);


/*
  Index utiles
  Objectif
  - aider les recherches et les jointures
*/
CREATE INDEX idx_covoiturage_statut_date
ON covoiturage (statut_covoiturage, date_heure_depart);

CREATE INDEX idx_covoiturage_date_depart
ON covoiturage (date_heure_depart);

CREATE INDEX idx_covoiturage_ville_depart
ON covoiturage (ville_depart);

CREATE INDEX idx_covoiturage_ville_arrivee
ON covoiturage (ville_arrivee);

CREATE INDEX idx_participation_covoiturage
ON participation (id_covoiturage);

CREATE INDEX idx_participation_utilisateur
ON participation (id_utilisateur);

CREATE INDEX idx_avis_participation
ON avis (id_participation);

COMMIT;
