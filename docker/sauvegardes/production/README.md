# Sauvegardes de production EcoRide

Ce dossier contient la procédure de sauvegarde applicative mise en place pour les bases de données de production EcoRide.

Les scripts locaux placés dans `docker/sauvegardes/` servent au développement local. Les fichiers de ce dossier concernent le VPS de production.

## 1. Objectif

La procédure sauvegarde les deux bases utilisées par EcoRide.

PostgreSQL contient les données relationnelles de l’application, comme les utilisateurs, les voitures, les covoiturages, les participations, les avis et les commissions.

MongoDB contient le journal des événements applicatifs.

Les sauvegardes générées ne sont pas stockées dans le dépôt Git. Elles sont écrites sur le VPS dans un dossier séparé du code.

Dossier du code de production :

`/srv/ecoride/ecoride/`

Dossier des sauvegardes de production :

`/srv/ecoride/sauvegardes/`

Cette séparation évite de mélanger les scripts versionnés avec des fichiers pouvant contenir des données personnelles.

## 2. Fichiers utilisés

Script de sauvegarde :

`docker/sauvegardes/production/sauvegarder_bases_production.sh`

Exemple de tâche cron :

`docker/sauvegardes/production/exemple_tache_cron.txt`

Le script utilise le fichier Docker Compose de production :

`docker-compose.production.yaml`

## 3. Fonctionnement du script

Le script lance une sauvegarde PostgreSQL et une sauvegarde MongoDB depuis les conteneurs Docker de production.

Pour PostgreSQL, il utilise `pg_dump`.

Pour MongoDB, il utilise `mongodump`.

Le script vérifie que les outils `pg_dump` et `mongodump` sont disponibles dans les conteneurs.

Il crée les dossiers de sauvegarde si nécessaire.

Il vérifie que les fichiers générés existent et qu’ils ne sont pas vides.

Il génère une empreinte SHA-256 pour chaque fichier de sauvegarde.

Il applique des droits restrictifs sur les dossiers et les fichiers.

Il supprime les anciennes sauvegardes selon la durée de conservation choisie.

## 4. Emplacement des fichiers générés

Les sauvegardes PostgreSQL sont écrites dans :

`/srv/ecoride/sauvegardes/postgresql/`

Les sauvegardes MongoDB sont écrites dans :

`/srv/ecoride/sauvegardes/mongodb/`

Les journaux d’exécution cron sont écrits dans :

`/srv/ecoride/sauvegardes/journaux/`

## 5. Sécurité

Les dossiers de sauvegarde sont limités au propriétaire avec `chmod 700`.

Les fichiers de sauvegarde sont limités au propriétaire avec `chmod 600`.

Les sauvegardes restent hors Git, car elles peuvent contenir des données personnelles liées aux utilisateurs, aux covoiturages, aux participations, aux avis ou aux événements applicatifs.

Les empreintes SHA-256 permettent de vérifier qu’un fichier de sauvegarde correspond bien au fichier contrôlé.

## 6. Conservation

Par défaut, le script conserve les sauvegardes applicatives pendant 7 jours.

La durée peut être changée en passant un nombre de jours au script.

Exemple :

`./docker/sauvegardes/production/sauvegarder_bases_production.sh 7`

La rotation limite l’accumulation de fichiers contenant des données personnelles.

## 7. Vérification manuelle

Le script a été testé manuellement sur le VPS avant l’automatisation.

Commande exécutée depuis le dossier du projet :

`./docker/sauvegardes/production/sauvegarder_bases_production.sh 7`

Après exécution, les fichiers PostgreSQL et MongoDB ont été vérifiés dans les dossiers de sauvegarde.

Lister les sauvegardes PostgreSQL :

`ls -lh /srv/ecoride/sauvegardes/postgresql/`

Lister les sauvegardes MongoDB :

`ls -lh /srv/ecoride/sauvegardes/mongodb/`

Les fichiers générés sont accompagnés d’une empreinte `.sha256`.

Les droits observés sur les fichiers sont restrictifs :

`rw-------`

Cela signifie que seul le propriétaire du fichier peut le lire et l’écrire.

## 8. Automatisation avec cron

La sauvegarde de production est automatisée avec cron sur le VPS.

La tâche est visible avec :

`crontab -l`

Planification installée :

`30 2 * * *`

Cette planification exécute la sauvegarde chaque jour à 02 h 30.

La tâche lance le script suivant avec une conservation de 7 jours :

`/srv/ecoride/ecoride/docker/sauvegardes/production/sauvegarder_bases_production.sh 7`

Les journaux sont écrits dans :

`/srv/ecoride/sauvegardes/journaux/sauvegardes.log`

Le journal de production a confirmé plusieurs exécutions automatiques réussies à 02 h 30.

Le journal indique aussi les fichiers générés pour PostgreSQL et MongoDB.

Contrôler le journal :

`tail -80 /srv/ecoride/sauvegardes/journaux/sauvegardes.log`

Contrôler les sauvegardes PostgreSQL :

`ls -lh /srv/ecoride/sauvegardes/postgresql/`

Contrôler les sauvegardes MongoDB :

`ls -lh /srv/ecoride/sauvegardes/mongodb/`

## 9. Sauvegardes Hostinger

Le VPS bénéficie aussi des sauvegardes proposées par Hostinger.

Ces sauvegardes protègent le serveur complet.

Les scripts EcoRide ajoutent une sauvegarde ciblée des bases de données. Cette sauvegarde permet de restaurer PostgreSQL ou MongoDB sans restaurer tout le VPS.

## 10. Test de restauration

Une sauvegarde doit être testée par restauration.

Le test doit se faire dans une base séparée ou dans un environnement de test.

La base de production ne doit pas servir d’environnement de test de restauration.

L’objectif du test est de vérifier que les fichiers générés sont réellement exploitables, et pas seulement présents sur le serveur.
