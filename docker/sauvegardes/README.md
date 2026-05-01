# Sauvegardes des bases de données EcoRide

Ce dossier contient les scripts de sauvegarde, de restauration et de nettoyage local des bases utilisées par EcoRide.

PostgreSQL contient les données relationnelles de l'application.

MongoDB contient le journal des événements.

Git versionne les scripts afin de conserver une procédure reproductible.

Les fichiers de sauvegarde générés sont stockés dans var/sauvegardes/.

Ce dossier est ignoré par Git car les sauvegardes peuvent contenir des données personnelles.

## Sauvegarder PostgreSQL

./docker/sauvegardes/sauvegarder_postgresql.sh

## Restaurer PostgreSQL

./docker/sauvegardes/restaurer_postgresql.sh var/sauvegardes/postgresql/nom_du_fichier.sql

## Sauvegarder MongoDB

./docker/sauvegardes/sauvegarder_mongodb.sh

## Restaurer MongoDB

./docker/sauvegardes/restaurer_mongodb.sh var/sauvegardes/mongodb/nom_du_fichier.archive.gz

## Nettoyer les anciennes sauvegardes locales

./docker/sauvegardes/nettoyer_sauvegardes.sh 7

En production, cette logique devrait être complétée par une planification, un stockage sécurisé et un test régulier de restauration.
