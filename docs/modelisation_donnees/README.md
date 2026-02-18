# Modélisation des données — Projet EcoRide

Ce dossier regroupe les éléments liés à la conception et à la structure de la base de données relationnelle de l’application EcoRide.

---

## Modèle conceptuel livré

### Diagramme entité-relation (ER)

*`diagramme_er_dbeaver.pdf`
*`diagramme_er_dbeaver.png`

Ce diagramme représente la structure réelle de la base PostgreSQL utilisée dans le projet :

*Entités principales : utilisateur, voiture, covoiturage, participation, avis, commission_plateforme
*Clés primaires et étrangères
*Relations et cardinalités
*Contraintes structurelles cohérentes avec le schéma SQL fourni

Il correspond exactement au schéma implémenté dans :

*docs/sql/01_schema.sql

---

## Implémentation SQL

Les scripts officiels fournis pour le jury sont :

*`docs/sql/01_schema.sql` → création de la base
*`docs/sql/02_donnees_demo.sql` → insertion de données
*`docs/sql/03_requetes_verification.sql` → requêtes de test

Ces fichiers démontrent la maîtrise du SQL sans utilisation exclusive de migrations ou fixtures.

---

## Archives

Le dossier `archives_brouillons/` contient :

*Anciennes versions de diagrammes
*Tentatives intermédiaires (MCD Merise, MLD standard, etc.)
*Fichiers de travail non retenus

Ils ne font pas partie des livrables officiels.

---

## Objectif de la modélisation

La modélisation vise à :

*Garantir l’intégrité des données
*Formaliser les règles métier (contraintes, statuts, validation)
*Assurer la cohérence entre base de données et logique applicative Symfony
*Séparer les données métier (PostgreSQL) de la journalisation technique (MongoDB)
