# Tests automatisés — EcoRide

Ce dossier contient les premiers tests automatisés du projet EcoRide.

Les tests ont été commencés pour vérifier des points simples et importants du projet. Ils ne couvrent pas encore tous les parcours de l’application.

## 1. Commande d’exécution

Les tests se lancent avec PHPUnit :

`php bin/phpunit`

Pour afficher une sortie plus lisible :

`php bin/phpunit --testdox`

Lors de la dernière vérification, PHPUnit a exécuté 3 tests et 4 assertions avec succès.

## 2. Tests existants

### AccueilTest.php

Type de test : test fonctionnel Symfony.

Ce test étend `WebTestCase`, qui permet de simuler une requête HTTP vers l’application.

Le test vérifie que la page d’accueil répond correctement à une requête `GET /`.

Ce test contrôle que :

* la route d’accueil existe ;
* le contrôleur répond ;
* le rendu de la page ne provoque pas d’erreur.

### MentionsLegalesTest.php

Type de test : test fonctionnel Symfony.

Ce test étend aussi `WebTestCase`.

Le test vérifie que la page des mentions légales répond correctement à une requête `GET /mentions-legales`.

Ce test contrôle que :

* la route des mentions légales existe ;
* le contrôleur répond ;
* la page s’affiche sans erreur HTTP.

### ConnexionPostgresqlTest.php

Type de test : test unitaire PHPUnit.

Ce test étend `TestCase`, sans démarrer le navigateur de test Symfony.

Le test vérifie le comportement du service `ConnexionPostgresql` quand la variable d’environnement `POSTGRES_PASSWORD` est vide.

Le résultat attendu est une `RuntimeException` avec un message explicite.

Ce test permet de vérifier qu’une configuration PostgreSQL incomplète est détectée avant de créer une connexion PDO.

### bootstrap.php

Ce fichier prépare l’environnement de test.

Il charge l’autoload Composer et initialise l’environnement Symfony à partir du fichier `.env`.

## 3. Différence entre les types de tests utilisés

Un test fonctionnel vérifie le comportement visible d’une partie de l’application. Dans EcoRide, les tests fonctionnels actuels ouvrent une URL avec le client de test Symfony et vérifient que la réponse HTTP est correcte.

Un test unitaire vérifie un comportement précis dans une classe isolée. Dans EcoRide, le test unitaire actuel vérifie une règle du service `ConnexionPostgresql` sans tester un parcours complet du site.

## 4. Couverture actuelle

Les tests automatisés actuels couvrent seulement une première partie du projet.

Ils couvrent :

* le chargement de la page d’accueil ;
* le chargement de la page des mentions légales ;
* un cas d’erreur de configuration PostgreSQL.

Ils ne couvrent pas encore tous les parcours EcoRide.

Parcours non couverts par ces tests automatisés :

* inscription ;
* connexion utilisateur ;
* recherche de covoiturage ;
* consultation du détail d’un covoiturage ;
* participation à un covoiturage ;
* publication d’un covoiturage ;
* gestion des voitures ;
* historique utilisateur ;
* annulation ;
* démarrage et terminaison d’un covoiturage ;
* avis ;
* espace employé ;
* espace administrateur ;
* journalisation MongoDB ;
* courriels automatiques ;
* API de géocodage.

## 5. Place des tests dans l’intégration continue

Le workflow GitHub Actions d’intégration continue exécute les tests avec la commande :

`php bin/phpunit`

Les tests font donc partie des vérifications automatisées du projet.

Leur rôle est de détecter rapidement une erreur sur les points déjà couverts.

## 6. Évolution prévue

La suite logique serait d’ajouter progressivement des tests sur les parcours métier les plus sensibles.

Les priorités seraient :

* tester la participation à un covoiturage ;
* tester les contrôles liés aux crédits ;
* tester les places disponibles ;
* tester les routes liées à l’authentification ;
* tester les actions principales du tableau de bord ;
* tester les traitements liés aux avis et aux incidents.

L’objectif est d’élargir la couverture sans prétendre que tous les parcours sont déjà testés.
