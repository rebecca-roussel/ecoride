# Déploiement — EcoRide

Ce dossier regroupe la **documentation de déploiement** de l’application **EcoRide** sur un **VPS Hostinger** avec **Docker** et **Docker Compose**.

## 1. Vue d’ensemble de la stack Docker Compose

### Services déployés

- `nginx` (conteneur `ecoride_nginx`) : serveur web
  - exposition : **`8080:80`** (site accessible via `http://IP_DU_VPS:8080`)
- `php` (conteneur `ecoride_php`) : exécution PHP (application Symfony)
  - utilise `.env.docker` + variables d’environnement (dont `MAILER_DSN`)
- `postgresql` (conteneur `ecoride_postgresql`) : base de données relationnelle
  - exposition : `5432:5432`
  - volume : `donnees_postgresql`
- `mongodb` (conteneur `ecoride_mongodb`) : base NoSQL (journal d’événements)
  - exposition : `27017:27017`
  - volume : `donnees_mongodb`
- `mailhog` (conteneur `ecoride_mailhog`) : capture des courriels (test)
  - exposition : `1025:1025` (SMTP)
  - exposition : `8025:8025` (interface web)

### Réseau Docker

- `ecoride_reseau` : réseau interne commun à tous les services

### Volumes Docker

- `donnees_postgresql` : persistance des données PostgreSQL
- `donnees_mongodb` : persistance des données MongoDB

## 2. Accès après déploiement

- Application EcoRide : `http://IP_DU_VPS:8080`
- MailHog (interface web) : `http://IP_DU_VPS:8025`

> Remarque : la configuration Docker Compose peut exposer des ports (8080, 8025, 5432, 27017).  
> L’accessibilité depuis Internet dépend du pare-feu (UFW) et de la configuration réseau du VPS.

## 3. Environnement de déploiement (VPS Hostinger)

Informations relevées sur le serveur :

- Fournisseur : VPS Hostinger
- Nom machine : `srv1324090`
- OS : Ubuntu 24.04.3 LTS
- Virtualisation : KVM
- Accès SSH : utilisateur `root`
- IP publique : `72.61.161.107`
- Pare-feu UFW : inactif

Versions installées :

- Docker : `28.2.2` (paquet `docker.io`)
- Docker Compose : `2.37.1` (paquet `docker-compose-v2`)

## 4. Réseau : ports nécessaires

Ports exposés par le `docker-compose.yml` :

- `8080/tcp` : application EcoRide (Nginx)
- `8025/tcp` : interface web MailHog (consultation des courriels capturés)
- `1025/tcp` : SMTP MailHog (utilisé par l’application pour envoyer les courriels de test)
- `5432/tcp` : PostgreSQL
- `27017/tcp` : MongoDB

Recommandation de sécurité (principe) :

- À exposer publiquement : `8080` (puis `80/443` en cas de passage HTTP/HTTPS), éventuellement `8025` si l’interface MailHog doit rester consultable à distance.
- À ne pas exposer publiquement : `5432` et `27017` (bases de données), `1025` (SMTP de test).  
  Ces services doivent idéalement rester accessibles uniquement depuis le serveur (ou un réseau privé).

État constaté côté VPS :

- Avant le démarrage des conteneurs, aucun service n’écoute sur ces ports (vérification via `ss -lntp`).

## 5. Accès au dépôt GitHub (SSH)

- Dépôt : `git@github.com:rebecca-roussel/ecoride.git`
- Authentification : clé SSH `~/.ssh/id_ed25519` (utilisateur `ecoride`)
- Test : `ssh -T git@github.com`

## 6. Déploiement (résumé opérationnel)

Après le clonage du dépôt sur le VPS :

- Préparer le fichier `.env` (UID/GID) si requis par Docker Compose
- Préparer le fichier `.env.docker` adapté au VPS (URI, secret, accès bases)
- Lancer les services : `docker compose up -d --build`
- Vérifier l’état : `docker compose ps`
- Vérifier les journaux : `docker compose logs`
- Vérifier l’écoute réseau : `ss -lntp`

Les étapes détaillées (commandes et contrôles) sont décrites dans la fiche de déploiement du dossier.
