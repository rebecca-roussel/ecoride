# Gestion des dysfonctionnements — EcoRide

Ce document décrit la démarche utilisée pour analyser et corriger un dysfonctionnement dans le projet EcoRide.

Un dysfonctionnement peut être un bug visible dans l’application, une erreur de configuration, un problème de déploiement, un test en échec, une erreur Docker, une erreur SQL ou un comportement inattendu dans un parcours utilisateur.

L’objectif est de ne pas corriger au hasard. Chaque problème doit être observé, isolé, corrigé puis vérifié.

## 1. Identifier le problème

La première étape consiste à décrire le symptôme de façon factuelle.

Il faut noter :

* la page ou la commande concernée ;
* l’action effectuée ;
* le résultat attendu ;
* le résultat obtenu ;
* le message d’erreur exact s’il existe ;
* l’environnement concerné, local ou production.

Exemples de preuves utiles :

* capture d’écran ;
* message d’erreur complet ;
* sortie de commande ;
* journal applicatif ;
* journal Docker ;
* journal Nginx ;
* journal de sauvegarde.

## 2. Reproduire le problème

Avant de corriger, il faut vérifier si le problème est reproductible.

Pour une page web, je reproduis le parcours depuis le début.

Pour une commande, je relance la commande dans le même environnement.

Pour un problème de production, je vérifie d’abord l’état du VPS et des conteneurs avant de modifier le code.

Commandes utiles selon le cas :

`git status --short`

`docker compose ps`

`php bin/console debug:router`

`php bin/phpunit --testdox`

`curl -I https://eco-ride.fr`

## 3. Isoler la zone concernée

Une fois le symptôme reproduit, je cherche quelle partie du projet est concernée.

Je distingue notamment :

* interface Twig ;
* JavaScript ;
* contrôleur Symfony ;
* service métier ;
* service de persistance PostgreSQL ;
* journalisation MongoDB ;
* configuration Docker ;
* configuration Nginx ;
* workflow GitHub Actions ;
* script de sauvegarde ou de déploiement.

Cette étape évite de modifier plusieurs fichiers sans savoir lequel est réellement concerné.

## 4. Formuler une hypothèse

Avant de corriger, je formule une hypothèse simple.

Exemples :

* la route appelée ne correspond pas au contrôleur attendu ;
* le formulaire envoie une donnée manquante ;
* le jeton CSRF est absent ou invalide ;
* une requête SQL reçoit un paramètre incorrect ;
* un service n’a pas accès à une variable d’environnement ;
* un conteneur Docker n’est pas lancé ;
* une commande est exécutée dans le mauvais environnement.

L’hypothèse doit pouvoir être vérifiée avec une commande, une lecture de fichier ou un test.

## 5. Corriger de façon limitée

La correction doit rester ciblée.

Je modifie le moins de fichiers possible pour éviter d’introduire un nouveau problème.

Avant une correction sensible, je vérifie l’état Git :

`git status --short`

Si la correction concerne la production, je travaille d’abord sur une branche dédiée, puis je vérifie le changement avant de le fusionner.

## 6. Vérifier après correction

Après la correction, je vérifie que le problème est résolu.

Les vérifications dépendent du type de problème.

Pour le code PHP :

`php bin/phpunit --testdox`

Pour les templates Twig :

`php bin/console lint:twig templates`

Pour le conteneur Symfony :

`php bin/console lint:container`

Pour Docker Compose :

`docker compose config`

Pour la production :

`curl -I https://eco-ride.fr`

Pour les sauvegardes :

`tail -80 /srv/ecoride/sauvegardes/journaux/sauvegardes.log`

`ls -lh /srv/ecoride/sauvegardes/postgresql/`

`ls -lh /srv/ecoride/sauvegardes/mongodb/`

## 7. Garder une trace

Quand le problème est corrigé, je conserve une trace de la résolution.

La trace peut être :

* un commit Git avec un message clair ;
* une documentation mise à jour ;
* une capture avant / après ;
* une sortie de commande ;
* une note dans la documentation technique.

Le commit doit expliquer l’action réalisée, sans phrase vague.

Exemple :

`Documenter les procédures de déploiement et de sauvegarde`

## 8. Prévenir la réapparition

Quand c’est possible, j’ajoute une prévention.

Cela peut être :

* un test automatisé ;
* une vérification dans un script ;
* une règle de validation côté serveur ;
* une documentation plus claire ;
* une commande de contrôle dans un README ;
* une vérification dans GitHub Actions.

L’objectif est de réduire le risque que le même problème revienne plus tard.

## 9. Exemple de démarche appliquée

Pour les sauvegardes de production, la démarche a été structurée ainsi :

* création d’un script de sauvegarde PostgreSQL et MongoDB ;
* test manuel du script sur le VPS ;
* vérification des fichiers générés ;
* vérification des droits restrictifs ;
* installation d’une tâche cron ;
* contrôle des journaux après exécution automatique ;
* documentation de la procédure dans le README de production.

Cette démarche montre que la correction ou la mise en place d’un traitement ne s’arrête pas à l’écriture du script. Elle comprend aussi la vérification, la preuve et la documentation.

## 10. Limite

Cette procédure décrit la démarche générale utilisée dans EcoRide.

Elle ne remplace pas un outil complet de suivi d’incidents. Pour un projet plus grand, les anomalies pourraient être suivies dans un outil dédié comme GitHub Issues, GitLab Issues, Jira ou Notion.
