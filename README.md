# EcoRide

**Plateforme de covoiturage écoresponsable** - Titre Professionnel Développeur Web et Web Mobile

EcoRide connecte conducteurs et passagers autour d'un objectif commun : réduire l'impact environnemental des déplacements en voiture. *Les covoiturages réalisés avec un véhicule électrique sont identifiés comme écologiques.*

---

## À propos de l’architecture

L’architecture repose sur Symfony côté serveur, Twig pour les vues, PostgreSQL pour les données métier et MongoDB pour le journal d’événements.

### Organisation générale

L’application est organisée autour de trois parties principales.

| Partie | Rôle |
|---|---|
| Contrôleurs | Recevoir les requêtes HTTP et orienter le parcours utilisateur |
| Vues Twig | Afficher les pages de l’application |
| Services | Regrouper la logique métier et l’accès aux données |

Les contrôleurs restent centrés sur le parcours utilisateur. Les traitements plus techniques sont placés dans les services.

### Accès aux données

EcoRide utilise PDO avec des requêtes préparées. Cette approche garde un contrôle direct sur les requêtes SQL et rend les échanges avec PostgreSQL plus visibles.

| Base | Rôle dans le projet |
|---|---|
| PostgreSQL 16 | Stocker les données métier structurées |
| MongoDB 7 | Conserver le journal opérationnel d’événements |

PostgreSQL gère :

- les utilisateurs,
- les véhicules,
- les covoiturages,
- les participations,
- les avis,
- les commissions.

Les règles les plus importantes sont aussi protégées au niveau de la base avec des contraintes, des fonctions et des triggers.

MongoDB garde une trace chronologique des actions importantes qui concerne par exemple :

- les connexions,
- les participations,
- les incidents,
- les modérations,
- les suspensions...

### Conteneurs Docker

L’environnement repose sur Docker Compose. Il lance les services nécessaires au projet.

| Service | Rôle |
|---|---|
| Nginx | Point d’entrée HTTP |
| PHP-FPM | Exécution de l’application Symfony |
| PostgreSQL | Données métier |
| MongoDB | Journal d’événements |
| MailHog | Tests des emails en local |

Les services communiquent dans le réseau interne Docker. PostgreSQL reste réservé aux conteneurs. MongoDB est publié seulement en local pour pouvoir l’ouvrir avec MongoDB Compass pendant le développement.

### Sécurité

| Domaine | Mesure appliquée |
|---|---|
| Mots de passe | Hachage BCrypt |
| Réinitialisation | Jeton `random_bytes` haché en SHA-256, stocké en base, usage unique |
| Infrastructure | Code source monté en `read-only` dans le conteneur Nginx |
| Formulaires | Protection CSRF Symfony Security |
| En-têtes HTTP | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` |
| Secrets | Fichiers `.env`, `.env.local`, `.env.docker`, `.env.prod` et `.env.test` exclus du dépôt via `.gitignore` |
| Droits réseau | PostgreSQL non exposé publiquement. Les ports publics de production sont gérés dans la configuration de déploiement VPS |

### Choix de conception

EcoRide ne repose pas sur un ORM. Les accès aux données sont écrits dans des services dédiés, avec des requêtes SQL explicites. Ce choix m’a aidée à relier directement les règles métier au schéma de base de données.

Les règles les plus sensibles, comme les crédits, les places disponibles et les commissions, sont protégées côté PostgreSQL. Cela évite de dépendre uniquement du code applicatif pour maintenir la cohérence des données.

---

## Prérequis

- Windows avec WSL2 activé (distribution Ubuntu recommandée) — ou Linux / macOS
- Docker Desktop avec intégration WSL2 activée
- Git
- Un navigateur web

Aucune installation locale de PHP, Symfony ou Composer n'est requise. Tous les services s'exécutent dans des conteneurs Docker.

---

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/rebecca-roussel/ecoride.git
cd ecoride
```

### 2. Construire et démarrer les conteneurs

```bash
docker compose up -d --build
```

Cette commande démarre cinq services : Nginx, PHP-FPM, PostgreSQL, MongoDB et MailHog.

### 3. Installer les dépendances PHP

```bash
docker compose exec php composer install
```

### 4. Initialiser la base de données PostgreSQL

Créer le schéma relationnel (tables, triggers, fonctions, contraintes) :

```bash
docker compose exec -T postgresql psql -U ecoride -d ecoride -f docs/sql/01_schema.sql
```

Charger les données de démonstration :

```bash
docker compose exec -T postgresql psql -U ecoride -d ecoride -f docs/sql/02_donnees_demo.sql
```

### 5. Vider le cache Symfony

```bash
docker compose exec php php bin/console cache:clear
```

---

## Accès à l'application

| Service | URL |
|---|---|
| Application en production | <https://www.eco-ride.fr> |
| Application en local | <http://localhost:8080> |
| MailHog en local | <http://localhost:8025> |

---

## Comptes de démonstration

Mot de passe commun à tous les comptes : **`Ecoride2026!`**

| Rôle | Email |
|---|---|
| Administrateur | <jose@ecoride.fr> |
| Employé | <sophie@ecoride.fr> |
| Employé suspendu | <thomas@ecoride.fr> |
| Chauffeur et passager | <muriel@ecoride.fr> |
| Chauffeur | <benjamin@ecoride.fr> |
| Passager | <raoul@ecoride.fr> |
| Passager | <nina@ecoride.fr> |
| Chauffeur et passager | <luc@ecoride.fr> |
| Passager suspendu | <emma@ecoride.fr> |

---

## Vérification de l'environnement

Contrôler l'état des conteneurs :

```bash
docker compose ps
```

Vérifier les extensions PHP actives :

```bash
docker compose exec -T php php -m | grep -E 'pdo_pgsql|intl|zip|mongodb'
```

Contrôler la cohérence du conteneur de services Symfony :

```bash
docker compose exec php php bin/console lint:container
```

Vérifier la validité du fichier `composer.json` :

```bash
docker compose exec php composer validate
```

Contrôler la réponse HTTP et les en-têtes de sécurité :

```bash
curl -I http://localhost:8080
```

Résultat attendu : `HTTP/1.1 200 OK` avec présence de `X-Frame-Options`, `X-Content-Type-Options` et `Referrer-Policy`.

---

## Architecture des conteneurs

```text
Navigateur
    │
    ▼
 Nginx :8080          (point d'entrée HTTP )
    │
    ▼
 PHP-FPM               (Symfony 7 -PHP 8.3)
    │
    ├──▶ PostgreSQL     (données métier - triggers et fonctions SQL)
    ├──▶ MongoDB        (journal opérationnel d'événements)
    └──▶ MailHog :8025  (simulation SMTP en condition de développement uniquement)
```

Tous les services communiquent via le réseau interne de Docker Compose. PostgreSQL n'est pas exposé sur la machine hôte. MongoDB est publié uniquement en local sur `127.0.0.1:27017`, ce qui permet son utilisation avec MongoDB Compass depuis le poste de développement.

---

## Structure du projet

```text
ecoride/
├── .github/
│   └── workflows/          # CI/CD GitHub Actions
├── bin/                    # Entrées console Symfony / PHPUnit
├── config/                 # Configuration Symfony (packages, routes, services)
├── src/
│   ├── Controller/         # Routes et orchestration des requêtes HTTP
│   ├── Service/            # Logique métier et accès aux données
│   └── Command/            # Commandes console Symfony
├── templates/              # Vues Twig
├── public/                 # Point d'entrée web (index.php) — exposé par Nginx
│   ├── css/
│   ├── js/
│   ├── images/
│   └── polices/            # Polices auto-hébergées 
├── docs/
│   ├── sql/                # Scripts SQL (schéma + données de démonstration)
│   ├── documentation_code/
│   ├── modelisation_donnees/
│   ├── interface/          # Maquettes
│   └── gestion_projet/
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
│   └── sauvegardes/         # Scripts de sauvegarde et restauration des bases de données
├── docker-compose.yaml
├── composer.lock
├── composer.json
├── tests/                  # Tests automatisés
├── .env                    # Variables d'environnement (valeurs de développement)
├── .env.prod
├── .env.test
├── phpunit.dist.xml
└── symfony.lock                
```

---

## Manuel d'utilisation

Le guide utilisateur est disponible ici : **`docs/manuel_utilisation.md`**.

---

## Sauvegardes des bases de données

Git versionne le code source du projet, mais les données persistantes de l’application vivent dans PostgreSQL et MongoDB.

EcoRide contient donc des scripts de sauvegarde, de restauration et de nettoyage local dans le dossier `docker/sauvegardes/`.

Les sauvegardes générées sont stockées dans `var/sauvegardes/`. Ce dossier est ignoré par Git car ces fichiers peuvent contenir des données personnelles.

La procédure complète est documentée dans `docker/sauvegardes/README.md`.

---

## Dépannage

**Les conteneurs ne démarrent pas :**

```bash
docker compose down -v
docker compose up -d --build
```

**Erreur de connexion à la base de données :**

```bash
docker compose exec -T postgresql psql -U ecoride -d ecoride -f docs/sql/01_schema.sql
docker compose exec -T postgresql psql -U ecoride -d ecoride -f docs/sql/02_donnees_demo.sql
```

**Cache Symfony à vider :**

```bash
docker compose exec php php bin/console cache:clear
```

**Vérifier les variables d'environnement PostgreSQL :**

```bash
docker compose exec postgresql printenv | grep '^POSTGRES_'
```

---

## Auteur

Rebecca Roussel
Projet réalisé dans le cadre du titre professionnel DWWM — Studi, promotion Juin / Juillet 2026.
