# EcoRide

**Plateforme de covoiturage écoresponsable** — Titre Professionnel Développeur Web et Web Mobile (RNCP TP-01280)

EcoRide connecte conducteurs et passagers autour d'un objectif commun : réduire l'impact environnemental des déplacements en voiture. Les trajets réalisés avec un véhicule électrique sont identifiés comme écologiques et mis en avant dans les résultats de recherche.

---

## Points forts de l'architecture

### Persistance polyglotte (SQL + NoSQL)

L'application repose sur deux bases de données aux rôles strictement distincts.

**PostgreSQL 16** gère l'ensemble des données métier structurées : utilisateurs, véhicules, covoiturages, participations, avis, commissions. L'intégrité métier est garantie au niveau de la base via des **triggers et fonctions SQL** qui prennent en charge automatiquement le débit des crédits, le calcul des commissions, la mise à jour des places disponibles et le verrouillage des prix après validation d'une participation. Ces règles s'appliquent indépendamment de la couche applicative.

**MongoDB 7** est utilisé exclusivement comme **journal opérationnel d'événements** (*Audit Trail*). Il ne stocke aucune donnée métier et n'alimente pas les statistiques de l'application. Son rôle est d'enregistrer une trace chronologique et exploitable des événements significatifs (connexions, participations, incidents, modérations, suspensions) pour permettre une reconstitution rapide de toute situation sensible.

### Sécurité

```text
| Domaine | Mesure appliquée |
|---|---|
| Mots de passe | Hachage BCrypt |
| Réinitialisation | Jeton `random_bytes` haché en SHA-256, stocké en base, usage unique |
| Infrastructure | Code source monté en `read-only` dans le conteneur Nginx |
| Formulaires | Protection CSRF (Symfony Security) |
| En-têtes HTTP | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` |
| Secrets | `.env.local` exclu du dépôt via `.gitignore` |
| Droits réseau | PostgreSQL non exposé sur la machine hôte ; MongoDB publié uniquement en local sur `127.0.0.1:27017` |
```

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

```text
| Service | URL |
|---|---|
| Application | http://localhost:8080 |
| MailHog (emails de test) | http://localhost:8025 |
```

---

## Comptes de démonstration

Mot de passe commun à tous les comptes : **`Ecoride2026!`**

```text
| Rôle | Email |
|---|---|
| Administrateur | jose@ecoride.fr |
| Employé | sophie@ecoride.fr |
| Employé | thomas@ecoride.fr |
| Utilisateur (chauffeur/passager) | muriel@ecoride.fr |
| Utilisateur (chauffeur/passager) | benjamin@ecoride.fr |
| Utilisateur (chauffeur/passager) | raoul@ecoride.fr |
| Utilisateur (passager) | nina@ecoride.fr |
| Utilisateur (passager) | luc@ecoride.fr |
| Utilisateur (passager) | emma@ecoride.fr |
```

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
 Nginx :8080          (point d'entrée HTTP — code source en read-only)
    │
    ▼
 PHP-FPM               (Symfony 7 — PHP 8.3)
    │
    ├──▶ PostgreSQL     (données métier — triggers et fonctions SQL)
    ├──▶ MongoDB        (journal opérationnel d'événements)
    └──▶ MailHog :8025  (simulation SMTP — développement uniquement)
```

Tous les services communiquent via le réseau interne de Docker Compose. PostgreSQL n'est pas exposé sur la machine hôte. MongoDB est publié uniquement en local sur `127.0.0.1:27017`, ce qui permet son utilisation avec MongoDB Compass depuis le poste de développement.

---

## Structure du projet

```text
ecoride/
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
├── config/                 # Configuration Symfony et services
├── docs/
│   ├── sql/                # Scripts SQL (schéma + données de démonstration)
│   ├── modelisation_donnees/
│   ├── interface/          # Maquettes
│   └── gestion_projet/
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── docker-compose.yml
├── composer.json
└── .env                    # Variables d'environnement (valeurs de développement)
```

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
