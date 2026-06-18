# Déploiement — EcoRide

Ce dossier regroupe la documentation de déploiement de l’application EcoRide sur un VPS avec Docker, Docker Compose, Nginx et HTTPS.

Le déploiement repose sur quatre fichiers principaux :

- `docker-compose.production.yaml` : décrit les services de production ;
- `docker/nginx/default.production.conf` : configure Nginx pour la production ;
- `docker/deploiement/deployer_production.sh` : script de déploiement utilisable sur le VPS ;
- `.github/workflows/deploiement_continu.yml` : workflow GitHub Actions de déploiement continu.

## 1. Configuration Docker Compose de production

Le fichier `docker-compose.production.yaml` déclare les services de production.

Services utilisés :

- `nginx` : serveur web exposé sur les ports 80 et 443 ;
- `php` : conteneur qui exécute l’application Symfony ;
- `postgresql` : base de données relationnelle ;
- `mongodb` : base NoSQL utilisée pour le journal d’événements.

Les données PostgreSQL et MongoDB sont conservées dans des volumes Docker.

## 2. Configuration Nginx de production

Le fichier `docker/nginx/default.production.conf` configure Nginx pour la production.

Il sert à :

- rediriger HTTP vers HTTPS ;
- utiliser les certificats Let’s Encrypt ;
- servir le dossier public de Symfony ;
- transmettre les requêtes PHP au conteneur `php` ;
- ajouter des en-têtes de sécurité HTTP.

## 3. Variables d’environnement

Le fichier `.env.production.local` doit exister sur le VPS.

Il contient les valeurs sensibles de production. Il ne doit pas être envoyé sur GitHub.

## 4. Script de déploiement

Le script de déploiement est situé ici :

`docker/deploiement/deployer_production.sh`

Il sert à déployer une version précise du projet à partir d’un tag Git.

Exemple d’utilisation sur le VPS :

`./docker/deploiement/deployer_production.sh v1.0.1`

Le script vérifie le tag, l’état Git du VPS, les fichiers de production, la configuration Docker Compose, l’état des conteneurs et la réponse du site.

En cas d’échec, le script revient à l’ancienne référence Git et relance les conteneurs.

## 5. Déploiement avec GitHub Actions

Le workflow `.github/workflows/deploiement_continu.yml` automatise le déploiement depuis GitHub.

Il peut être déclenché automatiquement avec un tag Git au format `vX.Y.Z`, ou manuellement avec `workflow_dispatch`.

Le workflow se connecte au VPS en SSH, déploie la version taguée et vérifie que l’application répond.

## 6. Commandes de contrôle

Vérifier la configuration Docker Compose :

`docker compose --env-file .env.production.local -f docker-compose.production.yaml config`

Relancer les conteneurs :

`docker compose --env-file .env.production.local -f docker-compose.production.yaml up -d --build --remove-orphans`

Afficher l’état des conteneurs :

`docker compose --env-file .env.production.local -f docker-compose.production.yaml ps`

Vérifier que le site répond :

`curl -fsSIL https://eco-ride.fr`

## 7. Sécurité

La production utilise HTTPS avec Let’s Encrypt.

Les données sensibles restent dans `.env.production.local` sur le VPS.

PostgreSQL et MongoDB ne sont pas exposés publiquement par le fichier Docker Compose de production.

Nginx ajoute des en-têtes de sécurité HTTP.

Le script bloque le déploiement si des modifications locales existent sur le VPS.

Le script prévoit un retour arrière en cas d’échec.

## 8. Document lié

La fiche détaillée de déploiement est disponible ici :

`docs/deploiement/deploiement_ecoride_sur_vps.pdf`
