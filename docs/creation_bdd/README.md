# Création de la base de données — EcoRide

Ce dossier contient les scripts SQL permettant de créer la base de données PostgreSQL du projet EcoRide.

La conception fonctionnelle et les schémas (ER, MCD, MLD) sont stockés séparément dans `docs/modelisation_donnees/`.
Ici, on retrouve uniquement l’implémentation SQL.

## Contenu du dossier

- `01_schema.sql` : création du schéma (tables, clés primaires, clés étrangères, contraintes, index).
- `02_donnees_test.sql` : données de test
- `03_requetes_test.sql` : requêtes de vérification

## Pré-requis

- PostgreSQL installé et en fonctionnement.
- Un client SQL, par exemple DBeaver.

## Exécution des scripts

L’exécution doit être réalisée dans l’ordre suivant :

1. Exécuter `01_schema.sql`
2. Exécuter `02_donnees_test.sql`
3. Exécuter `03_requetes_test.sql`

## Objectif

Ces scripts permettent de reproduire la base de données EcoRide à partir de zéro, afin de garantir :

- la cohérence avec le modèle de données,
- la reproductibilité du projet,
- la possibilité de tester rapidement l’application sur un environnement propre.
