# Architecture technique — EcoRide

Ce dossier contient le schéma d’architecture technique de l’application **EcoRide** au format **PNG** et **PDF**.

## Contenu du dossier

- une version image du schéma pour l’insertion rapide dans le dossier projet ou dans une présentation ;
- une version PDF pour une lecture plus propre, une impression ou un export.

## Rôle du document

Ce schéma présente le fonctionnement global de l’application EcoRide et la manière dont les principaux composants techniques communiquent entre eux.

Il permet de visualiser :

- l’arrivée d’une requête HTTP depuis le navigateur ;
- le rôle de **Nginx** comme serveur web et point d’entrée ;
- le passage par **PHP-FPM** pour l’exécution des scripts PHP ;
- le traitement applicatif par **Symfony** avec le rendu des vues **Twig** ;
- l’accès à **PostgreSQL** pour les données métier ;
- l’écriture dans **MongoDB** pour le journal d’événements ;
- l’utilisation de **MailHog** pour la simulation des envois d’e-mails en environnement de développement ;
- l’appel HTTP vers l’**API Géoplateforme** pour le géocodage des adresses ;
- le retour de la réponse HTML vers le navigateur.

## Lecture du schéma

Le schéma représente une architecture web de type client–serveur dans un environnement conteneurisé avec **Docker**.

### Flux principal

1. Le navigateur envoie une requête HTTP.
2. **Nginx** reçoit cette requête.
3. **Nginx** transmet les traitements dynamiques à **PHP-FPM** via **FastCGI**.
4. **Symfony** applique la logique métier et prépare la réponse.
5. Selon le besoin, l’application :
   - interroge **PostgreSQL** pour les données métier ;
   - écrit des événements dans **MongoDB** ;
   - envoie un message vers **MailHog** ;
   - appelle l’**API Géoplateforme** pour le géocodage.
6. La réponse HTML est renvoyée au navigateur via **PHP-FPM** et **Nginx**.

## Utilisation dans le projet

Ce document peut être utilisé :

- dans le **dossier projet** pour illustrer l’environnement technique ;
- dans les **annexes** comme preuve de compréhension de l’architecture ;
- dans une **présentation orale** pour expliquer simplement le trajet d’une requête dans l’application.

## Remarque

Ce schéma est un document de synthèse. Il sert à expliquer l’organisation technique générale du projet EcoRide. Il ne remplace pas les fichiers de configuration réels du projet, comme `docker-compose.yml`, la configuration Nginx ou les variables d’environnement.
