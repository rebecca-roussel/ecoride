# Environnement Docker – EcoRide

Ce document explique comment démarrer et vérifier l’environnement Docker du projet EcoRide.  
Objectif : exécuter l’application et ses services de manière reproductible.

## Services

Nginx : point d’entrée HTTP (accès navigateur).  
PHP : exécute Symfony (PHP-FPM).  
PostgreSQL : base relationnelle (données métier).  
MongoDB : journal d’événements (traces).

## Accès et ports

Application : `http://localhost:8080`  
PostgreSQL : `localhost:5432`  
MongoDB : `localhost:27018`

Entre conteneurs, les services se contactent via leurs noms Docker :

PostgreSQL : `postgresql`  
MongoDB : `mongodb`

## Variables d’environnement

Connexion PostgreSQL (utilisées par `src/Infrastructure/ConnexionPostgresql.php`) :

`PG_HOST`, `PG_PORT`, `PG_DB`, `PG_USER`, `PG_PASSWORD`

MongoDB (journal d’événements) :

`MONGODB_URI`, `MONGODB_BASE`, `MONGODB_COLLECTION`

## Démarrer / arrêter

À la racine du projet :
docker compose up -d --build

Arrêter l’environnement :
docker compose down

## Vérifications

Conteneurs actifs :
docker compose ps

PostgreSQL :
docker compose exec postgresql psql -U ecoride -d ecoride -c "SELECT 1;"

MongoDB :
docker compose exec mongodb mongosh --eval "db.runCommand({ ping: 1 })"

## Persistance des données
Les volumes Docker conservent les données entre les redémarrages :

donnees_postgresql

donnees_mongodb


