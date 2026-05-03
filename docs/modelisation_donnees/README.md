# Modélisation des données — Projet EcoRide

Ce dossier regroupe les documents liés à la conception et à la structure des données du projet EcoRide.

La base relationnelle principale de l’application repose sur PostgreSQL. MongoDB est utilisé séparément pour la journalisation applicative. Il ne fait donc pas partie du modèle relationnel PostgreSQL.

---

## Livrables de modélisation

### Modèle conceptuel des données

- `diagramme_er_bdd_ecoride.pdf`

Ce diagramme présente la logique métier des données utilisées dans EcoRide.

Il contient notamment les entités principales du projet :

- `utilisateur`
- `voiture`
- `covoiturage`
- `participation`
- `avis`
- `employe`
- `administrateur`
- `commission_plateforme`
- `reinitialisation_mot_de_passe`

Le bloc `journal_evenements_mongodb` est volontairement séparé. Il représente la collection MongoDB utilisée pour enregistrer les événements applicatifs. Il n’a pas de relation SQL avec les tables PostgreSQL.

---

### Représentation UML de la base

- `diagramme_uml_bdd_ecoride.pdf`

Ce diagramme donne une autre lecture de la structure des données. Il permet de visualiser les entités, leurs attributs et les liens principaux sous une forme plus proche d’un diagramme de classes.

---

### Modèle logique de données

- `diagramme_mld_bdd_ecoride.pdf`

Le MLD montre le passage vers une structure relationnelle. Les associations du modèle deviennent des clés étrangères dans les tables.

Ce document sert de support de conception. Le script SQL final reste la référence technique pour la création réelle de la base PostgreSQL.

---

### Schéma physique généré depuis PostgreSQL

- `diagramme_er_postgresql_dbeaver.pdf`

Ce diagramme est généré depuis DBeaver à partir de la base PostgreSQL reconstruite avec le script final.

Il sert à vérifier la structure réellement présente dans la base :

- tables créées ;
- clés primaires ;
- clés étrangères ;
- relations entre les tables ;
- cohérence avec le script `01_schema.sql`.

---

## Scripts SQL officiels

Les scripts SQL utilisés pour le projet sont placés dans le dossier :

- `docs/sql/`

Ils sont organisés ainsi :

- `01_schema.sql`  
  Création complète du schéma PostgreSQL avec les tables, contraintes, index, fonctions et déclencheurs.

- `02_donnees_demo.sql`  
  Insertion des données de démonstration utilisées pour tester les parcours principaux.

- `03_requetes_verification.sql`  
  Requêtes de contrôle utilisées pour vérifier les données, les statuts, les places, les crédits, les validations, les commissions et la modération des avis.

Le fichier `01_schema.sql` reste la référence officielle pour la création de la base. Les exports générés par Looping servent de support de conception, mais ils ne remplacent pas ce script.

---

## Script généré par Looping

- `script_ldd.txt`

Ce fichier correspond à une génération automatique depuis Looping. Il est conservé comme trace de conception.

Il ne remplace pas le script final `docs/sql/01_schema.sql`, car ce dernier contient les règles PostgreSQL complètes du projet, notamment les contraintes avancées, les déclencheurs, les index et les règles métier.

---

## Archives

Le dossier `archives_brouillons/` contient les anciennes versions de travail :

- anciens diagrammes ;
- essais intermédiaires ;
- fichiers Looping de brouillon ;
- versions non retenues.

Ces fichiers montrent le cheminement de conception, mais ils ne constituent pas les livrables finaux.

---

## Objectif de la modélisation

La modélisation des données m’a permis de préparer la structure de la base avant l’écriture du SQL.

Elle sert à :

- représenter les principales données métier ;
- clarifier les relations entre utilisateurs, voitures, covoiturages, participations et avis ;
- préparer les clés étrangères ;
- vérifier la cohérence entre la conception et la base PostgreSQL ;
- distinguer les données métier stockées dans PostgreSQL et la journalisation stockée dans MongoDB.

La version finale du modèle est donc construite autour de quatre preuves complémentaires :

- le modèle conceptuel ;
- le modèle logique ;
- le schéma physique généré depuis DBeaver ;
- les scripts SQL officiels du projet.
