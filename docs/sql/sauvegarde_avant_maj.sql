--
-- PostgreSQL database dump
--

\restrict veNOWMfQBflY1mUH9oO82m7uW41v8bbtiwzG1Qj9uYr3e3fwC87MOuxIvDzgh2O

-- Dumped from database version 16.11 (Debian 16.11-1.pgdg13+1)
-- Dumped by pg_dump version 16.11 (Debian 16.11-1.pgdg13+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: administrateur; Type: TABLE; Schema: public; Owner: ecoride
--

CREATE TABLE public.administrateur (
    id_utilisateur integer NOT NULL
);


ALTER TABLE public.administrateur OWNER TO ecoride;

--
-- Name: avis; Type: TABLE; Schema: public; Owner: ecoride
--

CREATE TABLE public.avis (
    id_avis integer NOT NULL,
    note integer NOT NULL,
    commentaire character varying(1000),
    date_depot timestamp without time zone NOT NULL,
    statut_moderation character varying(20) NOT NULL,
    id_participation integer NOT NULL,
    id_employe_moderateur integer,
    CONSTRAINT ck_avis_note CHECK (((note >= 1) AND (note <= 5))),
    CONSTRAINT ck_avis_statut CHECK (((statut_moderation)::text = ANY ((ARRAY['EN_ATTENTE'::character varying, 'VALIDE'::character varying, 'REFUSE'::character varying])::text[])))
);


ALTER TABLE public.avis OWNER TO ecoride;

--
-- Name: avis_id_avis_seq; Type: SEQUENCE; Schema: public; Owner: ecoride
--

ALTER TABLE public.avis ALTER COLUMN id_avis ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.avis_id_avis_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: covoiturage; Type: TABLE; Schema: public; Owner: ecoride
--

CREATE TABLE public.covoiturage (
    id_covoiturage integer NOT NULL,
    date_heure_depart timestamp without time zone NOT NULL,
    date_heure_arrivee timestamp without time zone NOT NULL,
    adresse_arrivee character varying(255) NOT NULL,
    adresse_depart character varying(255) NOT NULL,
    ville_depart character varying(80) NOT NULL,
    ville_arrivee character varying(80) NOT NULL,
    latitude_depart numeric(9,6),
    longitude_depart numeric(9,6),
    latitude_arrivee numeric(9,6),
    longitude_arrivee numeric(9,6),
    nb_places_dispo integer NOT NULL,
    prix_credits integer NOT NULL,
    commission_credits integer DEFAULT 2 NOT NULL,
    statut_covoiturage character varying(20) NOT NULL,
    incident_commentaire character varying(1000),
    incident_resolu boolean DEFAULT false NOT NULL,
    id_utilisateur integer NOT NULL,
    id_voiture integer NOT NULL,
    CONSTRAINT ck_adresse_arrivee_non_vide CHECK ((length(TRIM(BOTH FROM adresse_arrivee)) > 0)),
    CONSTRAINT ck_adresse_depart_non_vide CHECK ((length(TRIM(BOTH FROM adresse_depart)) > 0)),
    CONSTRAINT ck_covoiturage_commission_fixe CHECK ((commission_credits = 2)),
    CONSTRAINT ck_covoiturage_dates_coherentes CHECK ((date_heure_arrivee > date_heure_depart)),
    CONSTRAINT ck_covoiturage_nb_places_dispo CHECK (((nb_places_dispo >= 1) AND (nb_places_dispo <= 4))),
    CONSTRAINT ck_covoiturage_prix_credits CHECK ((prix_credits > 0)),
    CONSTRAINT ck_covoiturage_statut CHECK (((statut_covoiturage)::text = ANY ((ARRAY['PLANIFIE'::character varying, 'EN_COURS'::character varying, 'TERMINE'::character varying, 'ANNULE'::character varying, 'INCIDENT'::character varying])::text[]))),
    CONSTRAINT ck_geo_arrivee_pair CHECK ((((latitude_arrivee IS NULL) AND (longitude_arrivee IS NULL)) OR ((latitude_arrivee IS NOT NULL) AND (longitude_arrivee IS NOT NULL)))),
    CONSTRAINT ck_geo_depart_pair CHECK ((((latitude_depart IS NULL) AND (longitude_depart IS NULL)) OR ((latitude_depart IS NOT NULL) AND (longitude_depart IS NOT NULL)))),
    CONSTRAINT ck_incident_commentaire_obligatoire CHECK (((((statut_covoiturage)::text <> 'INCIDENT'::text) AND (incident_commentaire IS NULL) AND (incident_resolu = false)) OR (((statut_covoiturage)::text = 'INCIDENT'::text) AND (length(TRIM(BOTH FROM incident_commentaire)) > 0)))),
    CONSTRAINT ck_lat_arrivee CHECK (((latitude_arrivee IS NULL) OR ((latitude_arrivee >= ('-90'::integer)::numeric) AND (latitude_arrivee <= (90)::numeric)))),
    CONSTRAINT ck_lat_depart CHECK (((latitude_depart IS NULL) OR ((latitude_depart >= ('-90'::integer)::numeric) AND (latitude_depart <= (90)::numeric)))),
    CONSTRAINT ck_lon_arrivee CHECK (((longitude_arrivee IS NULL) OR ((longitude_arrivee >= ('-180'::integer)::numeric) AND (longitude_arrivee <= (180)::numeric)))),
    CONSTRAINT ck_lon_depart CHECK (((longitude_depart IS NULL) OR ((longitude_depart >= ('-180'::integer)::numeric) AND (longitude_depart <= (180)::numeric)))),
    CONSTRAINT ck_ville_arrivee_non_vide CHECK ((length(TRIM(BOTH FROM ville_arrivee)) > 0)),
    CONSTRAINT ck_ville_depart_non_vide CHECK ((length(TRIM(BOTH FROM ville_depart)) > 0))
);


ALTER TABLE public.covoiturage OWNER TO ecoride;

--
-- Name: covoiturage_id_covoiturage_seq; Type: SEQUENCE; Schema: public; Owner: ecoride
--

ALTER TABLE public.covoiturage ALTER COLUMN id_covoiturage ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.covoiturage_id_covoiturage_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: employe; Type: TABLE; Schema: public; Owner: ecoride
--

CREATE TABLE public.employe (
    id_utilisateur integer NOT NULL
);


ALTER TABLE public.employe OWNER TO ecoride;

--
-- Name: participation; Type: TABLE; Schema: public; Owner: ecoride
--

CREATE TABLE public.participation (
    id_participation integer NOT NULL,
    date_heure_confirmation timestamp without time zone NOT NULL,
    credits_utilises integer NOT NULL,
    est_annulee boolean DEFAULT false NOT NULL,
    id_utilisateur integer NOT NULL,
    id_covoiturage integer NOT NULL,
    CONSTRAINT ck_participation_credits_utilises CHECK ((credits_utilises > 0))
);


ALTER TABLE public.participation OWNER TO ecoride;

--
-- Name: participation_id_participation_seq; Type: SEQUENCE; Schema: public; Owner: ecoride
--

ALTER TABLE public.participation ALTER COLUMN id_participation ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.participation_id_participation_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: utilisateur; Type: TABLE; Schema: public; Owner: ecoride
--

CREATE TABLE public.utilisateur (
    id_utilisateur integer NOT NULL,
    pseudo character varying(50) NOT NULL,
    email character varying(255) NOT NULL,
    mot_de_passe_hash character varying(255) NOT NULL,
    credits integer DEFAULT 20 NOT NULL,
    role_chauffeur boolean DEFAULT false NOT NULL,
    role_passager boolean DEFAULT false NOT NULL,
    photo_path character varying(255),
    statut character varying(20) DEFAULT 'ACTIF'::character varying NOT NULL,
    date_changement_statut timestamp without time zone,
    CONSTRAINT ck_mdp_bcrypt_longueur CHECK ((length((mot_de_passe_hash)::text) = 60)),
    CONSTRAINT ck_utilisateur_au_moins_un_role CHECK ((role_chauffeur OR role_passager)),
    CONSTRAINT ck_utilisateur_credits_nonneg CHECK ((credits >= 0)),
    CONSTRAINT ck_utilisateur_email_non_vide CHECK ((length(TRIM(BOTH FROM email)) > 0)),
    CONSTRAINT ck_utilisateur_pseudo_non_vide CHECK ((length(TRIM(BOTH FROM pseudo)) > 0)),
    CONSTRAINT ck_utilisateur_statut CHECK (((statut)::text = ANY ((ARRAY['ACTIF'::character varying, 'SUSPENDU'::character varying])::text[])))
);


ALTER TABLE public.utilisateur OWNER TO ecoride;

--
-- Name: utilisateur_id_utilisateur_seq; Type: SEQUENCE; Schema: public; Owner: ecoride
--

ALTER TABLE public.utilisateur ALTER COLUMN id_utilisateur ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.utilisateur_id_utilisateur_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: voiture; Type: TABLE; Schema: public; Owner: ecoride
--

CREATE TABLE public.voiture (
    id_voiture integer NOT NULL,
    est_active boolean DEFAULT true NOT NULL,
    date_desactivation timestamp without time zone,
    immatriculation character varying(15) NOT NULL,
    date_1ere_mise_en_circulation date NOT NULL,
    marque character varying(50) NOT NULL,
    couleur character varying(30) NOT NULL,
    energie character varying(20) NOT NULL,
    nb_places integer NOT NULL,
    id_utilisateur integer NOT NULL,
    CONSTRAINT ck_voiture_couleur_non_vide CHECK ((length(TRIM(BOTH FROM couleur)) > 0)),
    CONSTRAINT ck_voiture_desactivation CHECK ((((est_active = true) AND (date_desactivation IS NULL)) OR ((est_active = false) AND (date_desactivation IS NOT NULL)))),
    CONSTRAINT ck_voiture_energie CHECK (((energie)::text = ANY ((ARRAY['ESSENCE'::character varying, 'DIESEL'::character varying, 'ETHANOL'::character varying, 'HYBRIDE'::character varying, 'ELECTRIQUE'::character varying])::text[]))),
    CONSTRAINT ck_voiture_immat_format CHECK ((((immatriculation)::text = upper((immatriculation)::text)) AND ((immatriculation)::text ~ '^[A-Z0-9]+$'::text))),
    CONSTRAINT ck_voiture_marque_non_vide CHECK ((length(TRIM(BOTH FROM marque)) > 0)),
    CONSTRAINT ck_voiture_nb_places CHECK (((nb_places >= 1) AND (nb_places <= 4)))
);


ALTER TABLE public.voiture OWNER TO ecoride;

--
-- Name: voiture_id_voiture_seq; Type: SEQUENCE; Schema: public; Owner: ecoride
--

ALTER TABLE public.voiture ALTER COLUMN id_voiture ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.voiture_id_voiture_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Data for Name: administrateur; Type: TABLE DATA; Schema: public; Owner: ecoride
--

COPY public.administrateur (id_utilisateur) FROM stdin;
1
\.


--
-- Data for Name: avis; Type: TABLE DATA; Schema: public; Owner: ecoride
--

COPY public.avis (id_avis, note, commentaire, date_depot, statut_moderation, id_participation, id_employe_moderateur) FROM stdin;
1	5	Trajet fluide, conduite agréable.	2026-02-05 10:30:00	VALIDE	1	2
\.


--
-- Data for Name: covoiturage; Type: TABLE DATA; Schema: public; Owner: ecoride
--

COPY public.covoiturage (id_covoiturage, date_heure_depart, date_heure_arrivee, adresse_arrivee, adresse_depart, ville_depart, ville_arrivee, latitude_depart, longitude_depart, latitude_arrivee, longitude_arrivee, nb_places_dispo, prix_credits, commission_credits, statut_covoiturage, incident_commentaire, incident_resolu, id_utilisateur, id_voiture) FROM stdin;
1	2026-02-05 08:00:00	2026-02-05 09:10:00	Gare Genève	Gare Annemasse	Annemasse	Genève	\N	\N	\N	\N	2	12	2	PLANIFIE	\N	f	3	1
2	2026-02-06 18:30:00	2026-02-06 19:20:00	Cornavin	Centre Annemasse	Annemasse	Genève	\N	\N	\N	\N	1	8	2	PLANIFIE	\N	f	4	2
\.


--
-- Data for Name: employe; Type: TABLE DATA; Schema: public; Owner: ecoride
--

COPY public.employe (id_utilisateur) FROM stdin;
2
\.


--
-- Data for Name: participation; Type: TABLE DATA; Schema: public; Owner: ecoride
--

COPY public.participation (id_participation, date_heure_confirmation, credits_utilises, est_annulee, id_utilisateur, id_covoiturage) FROM stdin;
1	2026-01-28 19:00:00	12	f	5	1
\.


--
-- Data for Name: utilisateur; Type: TABLE DATA; Schema: public; Owner: ecoride
--

COPY public.utilisateur (id_utilisateur, pseudo, email, mot_de_passe_hash, credits, role_chauffeur, role_passager, photo_path, statut, date_changement_statut) FROM stdin;
1	jose	jose@ecoride.fr	$2y$10$2yW4Acq9GFz6Y1t9EwL56nGisiWgNZq6ITZM5jtgUe52RvEJgwBuN	20	f	t	\N	ACTIF	\N
2	sophie	sophie@ecoride.fr	$2y$10$O6n9JEC3HqdZ6J6afU1zT0YQOaNF03vpUuT3em6KopZ9jZICffu4G	20	f	t	\N	ACTIF	\N
3	muriel	muriel@ecoride.fr	$2y$10$7FgtJsThJv07In9ZMJLsCfMZyuKpslm0lcNQqEefRWi4j7c1f5S71	20	t	t	photos/muriel.jpg	ACTIF	\N
4	benjamin	benjamin@ecoride.fr	$2y$10$IRz1THrHZp2n5RL08ALrCFQPS6YwfuNhFLOv2mpbUrhToxYkvB0dg	20	t	f	\N	ACTIF	\N
5	raoul	raoul@ecoride.fr	$2y$10$Yj2Soc0KO67IMRebhOmM1Khzfx1hcMbm9lThEnUZd7RbIBNg1qeoe	20	f	t	\N	ACTIF	\N
\.


--
-- Data for Name: voiture; Type: TABLE DATA; Schema: public; Owner: ecoride
--

COPY public.voiture (id_voiture, est_active, date_desactivation, immatriculation, date_1ere_mise_en_circulation, marque, couleur, energie, nb_places, id_utilisateur) FROM stdin;
1	t	\N	AA123BB	2021-03-10	TESLA	NOIR	ELECTRIQUE	3	3
2	t	\N	CC456DD	2016-06-22	RENAULT	BLEU	DIESEL	2	4
\.


--
-- Name: avis_id_avis_seq; Type: SEQUENCE SET; Schema: public; Owner: ecoride
--

SELECT pg_catalog.setval('public.avis_id_avis_seq', 1, true);


--
-- Name: covoiturage_id_covoiturage_seq; Type: SEQUENCE SET; Schema: public; Owner: ecoride
--

SELECT pg_catalog.setval('public.covoiturage_id_covoiturage_seq', 2, true);


--
-- Name: participation_id_participation_seq; Type: SEQUENCE SET; Schema: public; Owner: ecoride
--

SELECT pg_catalog.setval('public.participation_id_participation_seq', 1, true);


--
-- Name: utilisateur_id_utilisateur_seq; Type: SEQUENCE SET; Schema: public; Owner: ecoride
--

SELECT pg_catalog.setval('public.utilisateur_id_utilisateur_seq', 5, true);


--
-- Name: voiture_id_voiture_seq; Type: SEQUENCE SET; Schema: public; Owner: ecoride
--

SELECT pg_catalog.setval('public.voiture_id_voiture_seq', 2, true);


--
-- Name: administrateur administrateur_pkey; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.administrateur
    ADD CONSTRAINT administrateur_pkey PRIMARY KEY (id_utilisateur);


--
-- Name: avis avis_pkey; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.avis
    ADD CONSTRAINT avis_pkey PRIMARY KEY (id_avis);


--
-- Name: covoiturage covoiturage_pkey; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.covoiturage
    ADD CONSTRAINT covoiturage_pkey PRIMARY KEY (id_covoiturage);


--
-- Name: employe employe_pkey; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.employe
    ADD CONSTRAINT employe_pkey PRIMARY KEY (id_utilisateur);


--
-- Name: participation participation_pkey; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.participation
    ADD CONSTRAINT participation_pkey PRIMARY KEY (id_participation);


--
-- Name: avis uq_avis_participation; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.avis
    ADD CONSTRAINT uq_avis_participation UNIQUE (id_participation);


--
-- Name: participation uq_participation; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.participation
    ADD CONSTRAINT uq_participation UNIQUE (id_utilisateur, id_covoiturage);


--
-- Name: utilisateur utilisateur_email_key; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.utilisateur
    ADD CONSTRAINT utilisateur_email_key UNIQUE (email);


--
-- Name: utilisateur utilisateur_pkey; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.utilisateur
    ADD CONSTRAINT utilisateur_pkey PRIMARY KEY (id_utilisateur);


--
-- Name: utilisateur utilisateur_pseudo_key; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.utilisateur
    ADD CONSTRAINT utilisateur_pseudo_key UNIQUE (pseudo);


--
-- Name: voiture voiture_immatriculation_key; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.voiture
    ADD CONSTRAINT voiture_immatriculation_key UNIQUE (immatriculation);


--
-- Name: voiture voiture_pkey; Type: CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.voiture
    ADD CONSTRAINT voiture_pkey PRIMARY KEY (id_voiture);


--
-- Name: administrateur fk_administrateur_utilisateur; Type: FK CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.administrateur
    ADD CONSTRAINT fk_administrateur_utilisateur FOREIGN KEY (id_utilisateur) REFERENCES public.utilisateur(id_utilisateur);


--
-- Name: avis fk_avis_employe; Type: FK CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.avis
    ADD CONSTRAINT fk_avis_employe FOREIGN KEY (id_employe_moderateur) REFERENCES public.employe(id_utilisateur);


--
-- Name: avis fk_avis_participation; Type: FK CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.avis
    ADD CONSTRAINT fk_avis_participation FOREIGN KEY (id_participation) REFERENCES public.participation(id_participation);


--
-- Name: covoiturage fk_covoiturage_utilisateur; Type: FK CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.covoiturage
    ADD CONSTRAINT fk_covoiturage_utilisateur FOREIGN KEY (id_utilisateur) REFERENCES public.utilisateur(id_utilisateur);


--
-- Name: covoiturage fk_covoiturage_voiture; Type: FK CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.covoiturage
    ADD CONSTRAINT fk_covoiturage_voiture FOREIGN KEY (id_voiture) REFERENCES public.voiture(id_voiture);


--
-- Name: employe fk_employe_utilisateur; Type: FK CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.employe
    ADD CONSTRAINT fk_employe_utilisateur FOREIGN KEY (id_utilisateur) REFERENCES public.utilisateur(id_utilisateur);


--
-- Name: participation fk_participation_covoiturage; Type: FK CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.participation
    ADD CONSTRAINT fk_participation_covoiturage FOREIGN KEY (id_covoiturage) REFERENCES public.covoiturage(id_covoiturage);


--
-- Name: participation fk_participation_utilisateur; Type: FK CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.participation
    ADD CONSTRAINT fk_participation_utilisateur FOREIGN KEY (id_utilisateur) REFERENCES public.utilisateur(id_utilisateur);


--
-- Name: voiture fk_voiture_utilisateur; Type: FK CONSTRAINT; Schema: public; Owner: ecoride
--

ALTER TABLE ONLY public.voiture
    ADD CONSTRAINT fk_voiture_utilisateur FOREIGN KEY (id_utilisateur) REFERENCES public.utilisateur(id_utilisateur);


--
-- PostgreSQL database dump complete
--

\unrestrict veNOWMfQBflY1mUH9oO82m7uW41v8bbtiwzG1Qj9uYr3e3fwC87MOuxIvDzgh2O

