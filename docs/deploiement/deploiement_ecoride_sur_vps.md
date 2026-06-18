# Déploiement EcoRide sur VPS

Ce document décrit la procédure de déploiement de l’application EcoRide en production.

Il complète le fichier `docs/deploiement/README.md` avec une procédure plus détaillée. Il précise les fichiers utilisés, le rôle du script de déploiement, les contrôles réalisés et les points de sécurité liés à la mise en production.

## 1. Objectif du déploiement

L’objectif du déploiement est de rendre l’application EcoRide accessible en production sur le domaine `https://eco-ride.fr`.

Le projet est exécuté sur un VPS avec Docker et Docker Compose. Docker permet de lancer les services nécessaires dans des conteneurs, et Docker Compose permet de décrire ces services dans un fichier unique.

Le déploiement de production repose sur une version taguée du projet. Cela permet de déployer une version précise du code au lieu de déployer un état non identifié de la branche.

## 2. Fichiers utilisés

Le fichier `docker-compose.production.yaml` décrit les services utilisés en production.

Il déclare le serveur web `nginx`, le conteneur `php` pour Symfony, la base relationnelle `postgresql` et la base NoSQL `mongodb`.

Le fichier `docker/nginx/default.production.conf` configure Nginx pour la production. Il gère la redirection HTTP vers HTTPS, les certificats Let’s Encrypt, le dossier public de Symfony, le passage des requêtes PHP au conteneur `php` et plusieurs en-têtes de sécurité HTTP.

Le fichier `.env.production.local` doit être présent sur le VPS. Il contient les variables sensibles de production. Ce fichier reste local au serveur et ne doit pas être envoyé sur GitHub.

Le script `docker/deploiement/deployer_production.sh` permet de lancer un déploiement depuis le VPS. Il reçoit un tag Git, vérifie l’état du projet, relance Docker Compose et contrôle que le site répond.

Le workflow `.github/workflows/deploiement_continu.yml` permet de déclencher le déploiement depuis GitHub Actions. Il se connecte au VPS en SSH et exécute la procédure de déploiement pour une version taguée.

## 3. Services de production

La production utilise quatre services principaux.

`nginx` reçoit les requêtes HTTP et HTTPS. Il expose les ports 80 et 443. Le port 80 sert à recevoir les requêtes HTTP et à les rediriger vers HTTPS. Le port 443 sert à répondre en HTTPS.

`php` exécute l’application Symfony. Le code du projet est monté dans le conteneur et le fichier `.env.production.local` fournit les variables d’environnement nécessaires.

`postgresql` stocke les données relationnelles de l’application, comme les utilisateurs, les voitures, les covoiturages, les participations, les avis et les commissions.

`mongodb` stocke les événements applicatifs utilisés pour le journal d’activité.

PostgreSQL et MongoDB utilisent des volumes Docker afin de conserver les données même si les conteneurs sont recréés.

## 4. Script de déploiement

Le script de déploiement est situé dans le fichier suivant.

`docker/deploiement/deployer_production.sh`

Il s’utilise depuis le VPS avec un tag Git.

Exemple :

`./docker/deploiement/deployer_production.sh v1.0.1`

Le script commence par vérifier qu’un tag a été fourni. Sans tag, il s’arrête, car il ne sait pas quelle version déployer.

Il vérifie ensuite que le VPS ne contient pas de modifications locales suivies par Git. Cette vérification évite d’écraser un changement fait directement sur le serveur.

Le script garde en mémoire l’ancienne référence Git avant de déployer. Cette valeur permet de revenir à la version précédente si le déploiement échoue.

Le script récupère les tags du dépôt Git, vérifie que le tag demandé existe, puis place le projet sur ce tag.

Avant de relancer les conteneurs, il vérifie la présence du fichier `.env.production.local` et du fichier `docker-compose.production.yaml`.

Il contrôle ensuite la configuration Docker Compose avec la commande `docker compose config`.

Si la configuration est valide, il reconstruit et relance les conteneurs avec Docker Compose.

Après le redémarrage, il affiche l’état des conteneurs avec `docker compose ps`.

Il termine par un contrôle HTTP avec `curl -fsSIL https://eco-ride.fr`. Ce contrôle permet de vérifier que le site répond après le déploiement.

En cas d’erreur pendant la procédure, le script déclenche un retour arrière. Il replace le projet sur l’ancienne référence Git, relance les conteneurs et tente de vérifier à nouveau l’URL de production.

## 5. Déploiement avec GitHub Actions

Le workflow de déploiement continu se trouve dans le fichier suivant.

`.github/workflows/deploiement_continu.yml`

Il peut être lancé automatiquement quand un tag Git au format `vX.Y.Z` est poussé sur GitHub.

Il peut aussi être lancé manuellement depuis GitHub avec `workflow_dispatch`, en indiquant le tag à déployer.

Le workflow prépare la clé SSH à partir des secrets GitHub. Les secrets permettent de stocker les informations sensibles sans les écrire directement dans le dépôt.

Le workflow se connecte ensuite au VPS, vérifie les variables nécessaires, se place dans le dossier du projet et exécute la logique de déploiement.

## 6. Contrôles après déploiement

Après un déploiement, plusieurs contrôles permettent de vérifier l’état de la production.

La commande suivante vérifie la configuration Docker Compose.

`docker compose --env-file .env.production.local -f docker-compose.production.yaml config`

La commande suivante affiche l’état des conteneurs.

`docker compose --env-file .env.production.local -f docker-compose.production.yaml ps`

La commande suivante vérifie que le site répond.

`curl -fsSIL https://eco-ride.fr`

Ces contrôles permettent de vérifier que les services sont lancés et que l’application répond depuis l’extérieur.

## 7. Points de sécurité

Le déploiement utilise HTTPS avec des certificats Let’s Encrypt.

Nginx redirige les requêtes HTTP vers HTTPS.

Les variables sensibles restent dans `.env.production.local` sur le VPS.

PostgreSQL et MongoDB ne sont pas exposés publiquement dans le fichier Docker Compose de production. Ils communiquent avec l’application via le réseau Docker interne.

Nginx ajoute plusieurs en-têtes de sécurité HTTP, comme `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy` et `Strict-Transport-Security`.

Le script bloque le déploiement si des modifications locales existent sur le VPS.

Le script prévoit un retour arrière en cas d’échec.
