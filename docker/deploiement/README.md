# Déploiement production EcoRide

Ce dossier contient le script de déploiement manuel de production pour EcoRide.

Le script permet de déployer une version précise du projet sur le VPS à partir d’un tag Git.

Fichier concerné :

`docker/deploiement/deployer_production.sh`

## 1. Objectif du script

Le script sert à exécuter une procédure de déploiement reproductible depuis le VPS.

Il évite de lancer les commandes une par une à la main.

Le déploiement se fait à partir d’un tag Git, par exemple :

`v1.0.1`

Cela permet de déployer une version identifiée du projet.

## 2. Commande d’utilisation

Le script doit être lancé depuis la racine du projet sur le VPS.

Exemple :

`./docker/deploiement/deployer_production.sh v1.0.1`

Le tag est obligatoire.

Si aucun tag n’est fourni, le script s’arrête et affiche la syntaxe attendue.

## 3. Fichiers utilisés

Le script utilise par défaut :

`docker-compose.production.yaml`

`.env.production.local`

`https://eco-ride.fr`

Ces valeurs peuvent être changées avec des variables d’environnement si nécessaire :

`FICHIER_COMPOSE`

`FICHIER_ENV`

`URL_PRODUCTION`

Exemple :

`URL_PRODUCTION=https://eco-ride.fr ./docker/deploiement/deployer_production.sh v1.0.1`

## 4. Déroulement du script

Le script suit plusieurs étapes.

Il vérifie d’abord qu’un tag a été fourni.

Il vérifie ensuite que le VPS ne contient pas de modifications locales suivies par Git. Si un fichier suivi a été modifié directement sur le serveur, le script s’arrête pour éviter d’écraser ce changement.

Il garde en mémoire la référence Git actuellement déployée. Cette référence sert au retour arrière en cas d’échec.

Il récupère les dernières informations du dépôt Git, notamment les tags.

Il vérifie que le tag demandé existe.

Il place ensuite le projet sur ce tag avec `git checkout`.

Il vérifie que le fichier `.env.production.local` existe sur le VPS.

Il vérifie que le fichier `docker-compose.production.yaml` existe dans le projet.

Il contrôle la configuration Docker Compose avec :

`docker compose --env-file .env.production.local -f docker-compose.production.yaml config`

Il reconstruit et relance les conteneurs avec :

`docker compose --env-file .env.production.local -f docker-compose.production.yaml up -d --build --remove-orphans`

Il affiche l’état des conteneurs.

Il vérifie que le site répond avec :

`curl -fsSIL https://eco-ride.fr`

## 5. Retour arrière

Le script contient une fonction de retour arrière.

Si une erreur arrive pendant le déploiement, il tente de revenir à la référence Git précédente.

Il relance ensuite les conteneurs avec l’ancienne version.

Il tente aussi de vérifier que l’URL de production répond.

Ce retour arrière limite le risque de laisser l’application dans un état incomplet après un échec.

## 6. Ce que le script ne fait pas

Le script ne crée pas de tag Git.

Le script ne pousse pas le code sur GitHub.

Le script ne modifie pas les secrets de production.

Le script ne crée pas le fichier `.env.production.local`.

Le script ne remplace pas une sauvegarde des bases de données.

Avant un déploiement sensible, les sauvegardes PostgreSQL et MongoDB doivent être vérifiées séparément.

## 7. Vérifications utiles avant déploiement

Avant d’utiliser le script, vérifier l’état Git :

`git status --short`

Vérifier que le tag existe :

`git tag --list`

Vérifier la configuration Docker Compose :

`docker compose --env-file .env.production.local -f docker-compose.production.yaml config`

Vérifier l’état actuel des conteneurs :

`docker compose --env-file .env.production.local -f docker-compose.production.yaml ps`

## 8. Lien avec GitHub Actions

Le workflow GitHub Actions de déploiement continu est documenté dans :

`.github/workflows/README.md`

Le script de ce dossier fournit une procédure équivalente exécutable directement depuis le VPS.

Le workflow permet de déclencher le déploiement depuis GitHub.

Le script permet de lancer la procédure depuis le serveur.
