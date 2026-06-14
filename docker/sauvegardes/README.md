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

## Sauvegardes de production

Cette procédure locale est complétée par une procédure dédiée au VPS de production.

Les fichiers de production sont placés dans :

docker/sauvegardes/production/

La procédure de production prévoit une sauvegarde applicative de PostgreSQL et MongoDB, une automatisation par cron, un stockage hors dépôt Git, des droits restrictifs, une durée de conservation limitée et un test de restauration dans un environnement séparé.

Le VPS bénéficie aussi des sauvegardes proposées par Hostinger. Ces sauvegardes protègent le serveur complet. Les scripts EcoRide ajoutent une sauvegarde ciblée des bases de données.
