# Base de données – Scripts SQL EcoRide

Ce dossier contient l’ensemble des scripts SQL utilisés pour la création, l’alimentation et la vérification de la base de données du projet **EcoRide**.

L’objectif est de permettre la reproduction complète de la base de données, ainsi que la validation du bon fonctionnement du modèle relationnel à l’aide de requêtes de vérification.

---

## Contenu du dossier

### 01_schema.sql

Ce script contient la création complète du schéma de la base de données PostgreSQL :

- création des tables,
- définition des clés primaires et étrangères,
- contraintes d’intégrité (NOT NULL, CHECK, etc.).

Il doit être exécuté en premier.

---

### 02_donnees_demo.sql

Ce script permet d’insérer des données de démonstration cohérentes avec le modèle :

- utilisateurs et rôles (administrateur, employé),
- véhicules,
- covoiturages,
- participations,
- avis.

Ces données servent uniquement à illustrer le fonctionnement de l’application et à permettre les vérifications.

Il doit être exécuté après le script de création du schéma.

---

### 03_requetes_verification.sql

Ce script contient une série de requêtes `SELECT` permettant de vérifier :

- la présence des utilisateurs,
- l’attribution correcte des rôles,
- la possession de véhicules par certains utilisateurs,
- l’existence d’une participation pour un passager,
- le lien entre un avis, son auteur et l’employé modérateur.

Ces requêtes servent de **preuves de cohérence** entre les tables et s’appuient sur les clés étrangères définies dans le schéma.

Les alias utilisés dans les requêtes suivent une convention simple :

- les 4 premières lettres du nom de la table (ex. `util`, `voit`, `part`, `avis`),
  afin d’améliorer la lisibilité dans un contexte pédagogique.

---

### capture_ecran_requetes_sql/

Ce dossier contient les captures d’écran des résultats obtenus dans DBeaver lors de l’exécution des requêtes de vérification.

Elles constituent une preuve visuelle du bon fonctionnement de la base de données.

---

## Ordre d’exécution recommandé

1. `01_schema.sql`
2. `02_donnees_demo.sql`
3. `03_requetes_verification.sql`

---

## Outils utilisés

- PostgreSQL
- DBeaver

---
