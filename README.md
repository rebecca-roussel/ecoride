# EcoRide

Plateforme de covoiturage écoresponsable développée dans le cadre de l'Évaluation Certificative Finale (ECF).

## Fonctionnalités principales

* Inscription / connexion utilisateur
* Gestion des rôles (chauffeur / passager)
* Publication de covoiturages
* Recherche avec filtres avancés
* Système de crédits internes
* Gestion des participations
* Système d’avis modéré
* Journalisation des événements (MongoDB)

## Pré-requis

Avant de lancer l’application, les éléments suivants doivent être installés sur la machine :

* Windows avec WSL2 activé (Ubuntu utilisé pour le développement)

* Docker Desktop (avec intégration WSL2 activée)

* Git

* Un navigateur web

L’application repose sur les technologies suivantes, exécutées dans des conteneurs Docker :

* PHP 8.x

* Symfony

* PostgreSQL

* MongoDB

* Nginx

* MailHog

Symfony et ses dépendances sont installés via Composer dans le conteneur PHP.
Aucune installation globale de Symfony sur la machine hôte n’est requise.

## Architecture

L’application repose sur :

* Symfony (architecture MVC)
* PostgreSQL pour les données relationnelles
* MongoDB pour la journalisation
* Docker pour l’isolation des services
* Nginx comme serveur web

## Structure du projet

L’organisation du projet suit une architecture Symfony classique, adaptée à un environnement Dockerisé.

```text
ecoride/
│
├── src/                    # Code source Symfony
│   ├── Controller/         # Contrôleurs (routes et requêtes HTTP)
│   ├── Service/            # Services métier (persistance, journalisation, logique applicative)
│   └── Command/            # Commandes console Symfony
│
├── public/                 # Point d’entrée web (index.php)
│   ├── css/                # Feuilles de style
│   ├── js/                 # Scripts JavaScript
│   ├── images/             # Images statiques
│   ├── photos/             # Photos de profil (uploads utilisateurs)
│   ├── icones/             # Pictogrammes SVG
│   └── polices/            # Polices (auto-hébergement)
│
├── templates/              # Vues Twig (interface utilisateur)
│
├── config/                 # Configuration Symfony
│
├── docs/                   # Documentation du projet
│   ├── gestion_projet/     # Organisation, suivi, livrables (Kanban, User Story mapping, Cahier des charges...)
│   ├── interface/          # Maquettes et éléments d’interface
│   ├── modelisation_donnees/ # Modélisation (MCD/MLD)
│   ├── ressources/         # Ressources utiles (routes, test email)
│   ├── sql/                # Scripts SQL (schéma, données de démonstration, vérifications)
│   └── uml/                # Exports UML destinés à la documentation
│
├── docker/                 # Configuration Docker (PHP, Nginx)
│
├── docker-compose.yml      # Orchestration des conteneurs
├── composer.json           # Dépendances PHP
├── .env* / .env.docker     # Variables d’environnement (Symfony + Docker)
└── README.md               # Documentation principale
```

### Organisation technique

* **Controllers** : gèrent les routes et orchestrent les appels aux services.
* **Services** : contiennent la logique métier et l’accès aux bases de données.
* **Templates Twig** : assurent le rendu des pages.
* **PostgreSQL** : stocke les données relationnelles (utilisateurs, covoiturages, participations, avis, commissions).
* **MongoDB** : enregistre les événements applicatifs (journalisation).
* **Docker** : isole chaque service dans un conteneur dédié.

## Installation

### 1. Cloner le dépôt

```bash
git clone <https://github.com/rebecca-roussel/ecoride.git>
cd ecoride
```

### 2. Lancer les conteneurs Docker

```bash
docker compose up -d --build
```

### 3. Vérifier que les services sont actifs

```bash
docker compose ps
```

## Configuration (.env)

Les variables d’environnement sont définies dans :

* .env

* .env.docker

Elles configurent notamment :

* la connexion PostgreSQL

* la connexion MongoDB

* la configuration SMTP (MailHog en local)

* les paramètres Symfony

Pour un usage local, aucune modification n’est nécessaire.

## Base de données (PostgreSQL)

### 1. Création du schéma

```bash
docker compose exec -T postgresql psql -U ecoride -d ecoride -f docs/sql/01_schema.sql
```

### 2. Insertion des données de démonstration

```bash
docker compose exec -T postgresql psql -U ecoride -d ecoride -f docs/sql/02_donnees_demo.sql
```

Ce script insère :

* les utilisateurs

* les rôles administrateur et employé

* les véhicules

* les covoiturages

* les participations

* les avis

* les commissions plateforme

## Base de données NoSQL (MongoDB)

MongoDB est utilisé pour le journal des événements (traçabilité).

Les événements enregistrés concernent notamment :

* ouverture de pages

* recherches effectuées

* publications de covoiturages

* créations de participations

* annulations

* modérations

Aucune action manuelle n’est nécessaire : la base est utilisée automatiquement par l’application.

## Lancer l'application en local

Une fois les conteneurs actifs, l’application est accessible à l’adresse :

* <http://localhost:8080>

Interface MailHog (simulation des emails) :

* <http://localhost:8025>

## Tests / comptes de démo

Mot de passe commun à tous les comptes :

* Ecoride2026!

### Administrateur

* <jose@ecoride.fr>

### Employés

* <sophie@ecoride.fr>

* <thomas@ecoride.fr>

### Chauffeurs / Passagers

* <muriel@ecoride.fr>

* <benjamin@ecoride.fr>

* <raoul@ecoride.fr>

* <nina@ecoride.fr>

* <luc@ecoride.fr>

* <emma@ecoride.fr>

## Dépannage

* Les conteneurs ne démarrent pas :

```bash
docker compose down -v
docker compose up -d --build
```

* Problème de base de données :

```bash
docker compose exec -T postgresql psql -U ecoride -d ecoride -f docs/sql/01_schema.sql
docker compose exec -T postgresql psql -U ecoride -d ecoride -f docs/sql/02_donnees_demo.sql
```

* Cache Symfony :

```bash
docker compose exec -T php php bin/console cache:clear
```

## Documentation

* Modélisation des données : /docs/modelisation_donnees
* Scripts SQL : /docs/sql
* Diagrammes UML : /uml
* Maquettes interface : /docs/interface

## Auteur

Rebecca Roussel  
Projet réalisé dans le cadre du RNCP Développeur Web et Web Mobile – 2026
