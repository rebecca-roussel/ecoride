# Sauvegardes de production EcoRide

Ce dossier contient la procédure de sauvegarde applicative prévue pour le VPS de production EcoRide.

Les scripts locaux placés dans docker/sauvegardes/ servent au développement local. Les fichiers de ce dossier concernent la production.

## Objectif

La procédure sauvegarde les deux bases utilisées par EcoRide.

PostgreSQL contient les données métier de l'application.

MongoDB contient le journal des événements.

Les sauvegardes générées ne sont pas stockées dans le dépôt Git. Elles sont écrites sur le VPS dans un dossier séparé du code.

Dossier du code de production :

/srv/ecoride/ecoride/

Dossier des sauvegardes de production :

/srv/ecoride/sauvegardes/

Cette séparation évite de mélanger les scripts versionnés avec des fichiers pouvant contenir des données personnelles.

## Fichiers

sauvegarder_bases_production.sh lance une sauvegarde PostgreSQL et MongoDB depuis les conteneurs Docker de production.

exemple_tache_cron.txt contient un exemple de tâche planifiée pour automatiser la sauvegarde chaque jour à 02 h 30.

## Fonctionnement

Le script utilise docker-compose.production.yaml.

Il exécute pg_dump dans le conteneur PostgreSQL.

Il exécute mongodump dans le conteneur MongoDB.

Il vérifie que les fichiers créés existent et qu'ils ne sont pas vides.

Il génère une empreinte SHA-256 pour chaque fichier de sauvegarde.

## Sécurité

Les dossiers de sauvegarde sont créés avec des droits restrictifs.

Les dossiers sont limités au propriétaire avec chmod 700.

Les fichiers de sauvegarde sont limités au propriétaire avec chmod 600.

Les sauvegardes restent hors Git, car elles peuvent contenir des données personnelles liées aux utilisateurs, aux covoiturages, aux participations, aux avis ou aux événements applicatifs.

## Conservation

Par défaut, le script conserve les sauvegardes applicatives pendant 7 jours.

La durée peut être changée en passant un nombre de jours au script.

Exemple :

./docker/sauvegardes/production/sauvegarder_bases_production.sh 7

La rotation limite l'accumulation de fichiers contenant des données personnelles.

## Automatisation

L'automatisation se fait avec cron sur le VPS.

Le fichier exemple_tache_cron.txt fournit la ligne à installer avec crontab -e.

Avant d'automatiser la tâche, le script doit être testé manuellement sur le VPS.

## Sauvegarde Hostinger

Le VPS bénéficie aussi des sauvegardes proposées par Hostinger.

Ces sauvegardes protègent le serveur complet.

Les scripts EcoRide ajoutent une sauvegarde ciblée des bases de données. Cette sauvegarde permet de restaurer PostgreSQL ou MongoDB sans restaurer tout le VPS.

## Test de restauration

Une sauvegarde doit être testée par restauration.

Le test doit se faire dans une base séparée ou dans un environnement de test.

La base de production ne doit pas servir d'environnement de test de restauration.
