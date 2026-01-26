# Modélisation des données — EcoRide

Ce dossier regroupe l’ensemble des travaux de modélisation des données du projet EcoRide.
Il constitue la base conceptuelle et logique ayant servi à la création de la base de données.

La modélisation a été réalisée de manière progressive, en respectant les étapes classiques
de conception des bases de données relationnelles.

## Objectif de la modélisation

L’objectif est de définir une structure de données :

- cohérente avec les besoins fonctionnels de l’application,
- indépendante de toute implémentation technique,
- suffisamment précise pour permettre une traduction fidèle en SQL.

Les scripts SQL correspondants sont disponibles séparément dans le dossier `docs/creation_bdd/`.

## Contenu du dossier

### Diagramme Entité-Relation (notation Chen)

- Fichiers : `.drawio`, `.pdf`, `.png`
- Rôle : identifier les entités métier, leurs attributs et leurs relations.
- Ce diagramme met en évidence les dépendances fondamentales du domaine (utilisateurs, covoiturages, participations, avis, etc.).

### Modèle Conceptuel de Données (MCD — méthode Merise)

- Fichiers : `.pdf`, `.jpg`
- Rôle : formaliser les entités et associations avec leurs cardinalités.
- Le MCD est indépendant de tout SGBD et constitue une étape de validation conceptuelle.

### Modèle Logique de Données (MLD)

- Fichiers : `.pdf`, `.jpg`
- Rôle : traduire le MCD en structure relationnelle (tables, clés primaires, clés étrangères).
- Ce modèle est directement exploitable pour la génération du schéma SQL.

### Source de modélisation

- Fichier : `modele_relationnel_looping.loo`
- Rôle : fichier source de travail utilisé avec le logiciel Looping.
- Il permet de modifier ou faire évoluer l’ensemble des modèles sans perte d’information.

## Méthodologie suivie

1. Analyse des besoins fonctionnels (cas d’usage, règles métier).
2. Élaboration du diagramme Entité-Relation (Chen).
3. Construction du Modèle Conceptuel de Données (Merise).
4. Passage au Modèle Logique de Données.
5. Vérification de la cohérence avant implémentation SQL.

## Portée

Ce dossier décrit **la structure des données**, mais ne contient aucun script d’exécution.
Il sert de référence de conception pour :

- la création de la base de données,
- la maintenance,
- l’évolution future du modèle.
