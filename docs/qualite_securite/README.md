# Qualité, sécurité et tests — EcoRide

Ce document regroupe les contrôles qualité, les mesures de sécurité et les tests réalisés dans le projet EcoRide.

Il ne présente pas le projet comme entièrement couvert par des tests automatisés. Il décrit ce qui existe actuellement, ce qui est vérifié, et les limites connues.

## 1. Contrôles qualité automatisés

Le projet utilise un workflow GitHub Actions d’intégration continue.

Fichier concerné :

`.github/workflows/integration_continue.yml`

Ce workflow vérifie plusieurs points du projet :

* installation de PHP 8.3 ;
* installation des extensions PHP nécessaires ;
* installation des dépendances Composer ;
* vérification de Docker Compose ;
* vérification du conteneur Symfony ;
* vérification de la syntaxe Twig ;
* vérification de la syntaxe PHP ;
* exécution des tests PHPUnit.

L’objectif est de détecter rapidement une erreur avant d’intégrer le code dans une branche importante.

## 2. Tests automatisés existants

Les tests automatisés se trouvent dans le dossier :

`tests/`

La commande utilisée est :

`php bin/phpunit`

Une sortie plus lisible peut être obtenue avec :

`php bin/phpunit --testdox`

À ce stade, la suite PHPUnit contient 3 tests et 4 assertions.

Tests existants :

* `AccueilTest.php` ;
* `MentionsLegalesTest.php` ;
* `ConnexionPostgresqlTest.php`.

`AccueilTest.php` et `MentionsLegalesTest.php` sont des tests fonctionnels Symfony. Ils utilisent `WebTestCase` pour simuler une requête HTTP et vérifier que les pages répondent correctement.

`ConnexionPostgresqlTest.php` est un test unitaire PHPUnit. Il vérifie le comportement du service `ConnexionPostgresql` quand la variable `POSTGRES_PASSWORD` est vide.

Les tests automatisés ne couvrent pas encore tous les parcours de l’application.

## 3. Jeu d’essai fonctionnel SQL

Le projet contient un jeu d’essai SQL dans le dossier :

`docs/sql/`

Les fichiers principaux sont :

* `01_schema.sql` ;
* `02_donnees_demo.sql` ;
* `03_requetes_verification.sql`.

Le fichier `03_requetes_verification.sql` sert à vérifier plusieurs règles importantes du schéma EcoRide :

* volumes de données ;
* rôles internes ;
* places disponibles ;
* crédits ;
* annulations ;
* statuts de validation ;
* incidents ;
* commissions ;
* modération des avis.

Les captures de vérification SQL sont conservées dans :

`docs/sql/capture_ecran_requetes_sql/`

## 4. Mesures de sécurité dans l’application

Plusieurs mesures de sécurité sont présentes dans le code.

Les formulaires sensibles utilisent des jetons CSRF. Ces jetons permettent de vérifier qu’une action POST vient bien d’un formulaire prévu par l’application.

Les mots de passe sont traités avec `password_hash()` lors de la création ou de la réinitialisation, et avec `password_verify()` lors de la connexion.

Les accès PostgreSQL utilisent PDO avec des requêtes préparées. Les méthodes `prepare()` et `execute()` séparent la requête SQL des valeurs envoyées.

Les traitements sensibles utilisent aussi des transactions, avec `beginTransaction()`, `commit()` et `rollBack()`.

Certaines requêtes utilisent `FOR UPDATE` pour verrouiller temporairement des lignes sensibles pendant un traitement, par exemple lors d’une participation ou d’un changement de statut.

Les erreurs métier sont gérées avec des exceptions explicites, notamment `RuntimeException`.

## 5. Sécurité côté serveur web

La configuration Nginx de production se trouve ici :

`docker/nginx/default.production.conf`

Elle ajoute plusieurs en-têtes de sécurité HTTP, notamment :

* `Strict-Transport-Security` ;
* `X-Content-Type-Options` ;
* `X-Frame-Options` ;
* `Referrer-Policy` ;
* `Content-Security-Policy` ;
* `Permissions-Policy`.

La production utilise HTTPS avec les certificats Let’s Encrypt.

Le trafic HTTP est redirigé vers HTTPS.

PostgreSQL et MongoDB ne sont pas exposés publiquement dans le fichier Docker Compose de production.

## 6. Contrôles de sécurité réalisés

Un audit des dépendances Composer a été exécuté avec :

`composer audit`

Le résultat obtenu indique :

`No security vulnerability advisories found.`

Un contrôle par recherche dans le code a aussi été utilisé pour repérer certains usages sensibles ou dangereux, comme `eval`, `shell_exec`, `system`, `exec`, `unserialize`, ou des requêtes SQL construites directement avec des données utilisateur.

Ces contrôles ne remplacent pas un test d’intrusion complet. Ils constituent des contrôles de sécurité ciblés adaptés au niveau actuel du projet.

## 7. Documentation du code

Le code contient des commentaires et des docblocks PHP.

Les docblocks utilisent des balises standards comme :

* `@param` ;
* `@return` ;
* `@throws`.

Ces balises sont en anglais, car elles font partie de la convention PHPDocumentor.

Les explications sont majoritairement rédigées en français afin de rester compréhensibles dans le contexte du projet.

## 8. Limites connues

Tous les parcours EcoRide ne sont pas encore couverts par des tests automatisés.

Les tests automatisés actuels ne couvrent pas encore :

* l’inscription ;
* la connexion complète ;
* la recherche de covoiturage ;
* la participation ;
* la publication d’un covoiturage ;
* la gestion des voitures ;
* l’historique ;
* les avis ;
* les incidents ;
* les espaces employé et administrateur ;
* la journalisation MongoDB ;
* les courriels automatiques ;
* l’API de géocodage.

Les tests de sécurité réalisés sont des contrôles ciblés. Le projet ne contient pas encore de campagne complète de tests d’intrusion.

## 9. Évolutions prévues

Les prochaines améliorations possibles sont :

* ajouter des tests fonctionnels sur les parcours principaux ;
* ajouter des tests unitaires sur les services métier sensibles ;
* documenter les scénarios de tests manuels ;
* compléter les contrôles de sécurité ;
* vérifier régulièrement les dépendances avec `composer audit` ;
* conserver les preuves de vérification après chaque mise à jour importante.

## 10. Documents complémentaires

Deux documents complètent ce README.

La matrice de traçabilité relie les fonctionnalités prévues dans le dossier de conception aux traitements présents dans le code :

`docs/qualite_securite/matrice_fonctionnalites_traitements.md`

La procédure de gestion des dysfonctionnements décrit la démarche suivie pour analyser, corriger, vérifier et documenter un problème :

`docs/qualite_securite/gestion_dysfonctionnements.md`

Ces documents servent à répondre plus précisément aux critères d’évaluation sur la conformité des traitements et la démarche structurée de résolution de problème.
