# Workflows GitHub Actions — EcoRide

Ce dossier contient les workflows GitHub Actions utilisés pour contrôler et déployer le projet EcoRide.

GitHub Actions permet d’exécuter automatiquement des commandes à certains moments du cycle de développement. Dans EcoRide, les workflows servent à deux choses principales :

* vérifier la qualité du code avant intégration ;
* déployer une version taguée sur le VPS de production.

## 1. Intégration continue

Fichier :

`integration_continue.yml`

Ce workflow sert à vérifier le projet automatiquement.

Il se déclenche dans deux cas :

* lors d’un push sur `develop`, `main`, `correction/**`, `fonctionnalite/**` ou `amelioration/**` ;
* lors d’une pull request vers `develop` ou `main`.

Il exécute plusieurs contrôles :

* récupération du dépôt ;
* installation de PHP 8.3 ;
* installation des extensions PHP nécessaires ;
* vérification de Composer ;
* installation des dépendances PHP ;
* vérification de Docker Compose ;
* vérification du conteneur Symfony ;
* vérification de la syntaxe Twig ;
* vérification de la syntaxe PHP ;
* exécution des tests PHPUnit.

Ce workflow ne déploie pas l’application. Il sert à détecter les erreurs avant d’intégrer le code dans une branche importante.

## 2. Déploiement continu

Fichier :

`deploiement_continu.yml`

Ce workflow sert à déployer EcoRide sur le VPS de production.

Il se déclenche dans deux cas :

* automatiquement quand un tag Git au format `vX.Y.Z` est poussé ;
* manuellement depuis GitHub Actions avec `workflow_dispatch`.

Le déploiement n’est donc pas lancé à chaque push sur une branche. Il est contrôlé par tag afin de déployer une version identifiée du projet.

Le workflow réalise les actions suivantes :

* déterminer le tag à déployer ;
* préparer la connexion SSH vers le VPS ;
* vérifier les secrets et variables nécessaires ;
* se connecter au VPS ;
* vérifier l’état Git du projet sur le serveur ;
* récupérer les tags ;
* placer le projet sur la version taguée ;
* vérifier les fichiers de production ;
* vérifier la configuration Docker Compose ;
* reconstruire et relancer les conteneurs ;
* afficher l’état des conteneurs ;
* vérifier que l’URL de production répond ;
* revenir à l’ancienne version en cas d’échec.

## 3. Différence entre intégration continue et déploiement continu

L’intégration continue vérifie le projet.

Le déploiement continu met une version en production.

Dans EcoRide, l’intégration continue peut se lancer souvent, sur plusieurs branches de travail. Le déploiement continu est plus contrôlé : il passe par un tag Git ou un lancement manuel.

## 4. Secrets GitHub utilisés pour le déploiement

Le workflow de déploiement utilise des secrets GitHub pour éviter d’écrire des informations sensibles dans le dépôt.

Secrets attendus :

* `VPS_CLE_SSH` : clé privée SSH utilisée pour se connecter au VPS ;
* `VPS_PORT_SSH` : port SSH du VPS ;
* `VPS_HOTE` : adresse du VPS ;
* `VPS_UTILISATEUR` : utilisateur SSH ;
* `VPS_CHEMIN_PROJET` : chemin du projet EcoRide sur le VPS ;
* `URL_PRODUCTION` : URL utilisée pour vérifier que le site répond.

Ces valeurs doivent être configurées dans GitHub et ne doivent pas être écrites dans les fichiers versionnés.

## 5. Commandes utiles

Vérifier l’état des workflows modifiés :

`git status --short`

Vérifier les erreurs d’espaces dans les fichiers modifiés :

`git diff --check`

Afficher le workflow d’intégration continue :

`sed -n '1,220p' .github/workflows/integration_continue.yml`

Afficher le workflow de déploiement continu :

`sed -n '1,280p' .github/workflows/deploiement_continu.yml`
