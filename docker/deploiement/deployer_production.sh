#!/usr/bin/env bash

# Script de déploiement production EcoRide
#
# Ce script sert à déployer une version précise du projet sur le VPS.
# La version à déployer est indiquée avec un tag Git, par exemple v1.0.1.
#
# Exemple d'utilisation sur le VPS :
# ./docker/deploiement/deployer_production.sh v1.0.1

# Arrête le script dès qu'une erreur arrive.
# Cela évite de continuer un déploiement incomplet.
set -euo pipefail

# Le script attend un seul argument : le tag Git à déployer.
if [ "$#" -ne 1 ]; then
  echo "Usage : $0 vX.Y.Z"
  exit 1
fi

TAG_DEPLOIEMENT="$1"

# Fichiers et URL utilisés pour la production.
# Ces valeurs peuvent être changées si besoin avec des variables d'environnement.
URL_PRODUCTION="${URL_PRODUCTION:-https://eco-ride.fr}"
FICHIER_ENV="${FICHIER_ENV:-.env.production.local}"
FICHIER_COMPOSE="${FICHIER_COMPOSE:-docker-compose.production.yaml}"

# Avant de déployer, je vérifie que le VPS n'a pas de modification locale.
# Si un fichier suivi par Git a été modifié directement sur le serveur,
# le script s'arrête pour éviter d'écraser ce changement.
if [ -n "$(git status --short --untracked-files=no)" ]; then
  echo "Des modifications locales existent sur le VPS."
  echo "Le déploiement est arrêté pour éviter d'écraser un changement."
  git status --short --untracked-files=no
  exit 1
fi

# Je garde en mémoire la version actuellement utilisée.
# Elle servira si je dois revenir en arrière après une erreur.
ancienne_reference="$(git rev-parse HEAD)"

# Cette fonction relance les conteneurs Docker de production.
relancer_conteneurs() {
  docker compose --env-file "$FICHIER_ENV" -f "$FICHIER_COMPOSE" up -d --build --remove-orphans
}

# Cette fonction est appelée si une erreur arrive pendant le déploiement.
# Elle remet le projet sur l'ancienne version et relance les conteneurs.
retour_arriere() {
  code_erreur="$?"

  if [ "$code_erreur" -ne 0 ]; then
    echo "Le déploiement a échoué."
    echo "Retour à la version précédente : $ancienne_reference"

    trap - ERR

    git checkout "$ancienne_reference" || true
    relancer_conteneurs || true
    curl -fsSIL "$URL_PRODUCTION" >/dev/null || true

    exit "$code_erreur"
  fi
}

# À partir d'ici, si une erreur arrive, le retour arrière sera lancé.
trap retour_arriere ERR

# Je récupère les dernières informations du dépôt Git,
# en particulier les tags disponibles.
git fetch --all --tags --prune

# Je vérifie que le tag demandé existe bien.
if ! git rev-parse -q --verify "refs/tags/$TAG_DEPLOIEMENT" >/dev/null; then
  echo "Le tag $TAG_DEPLOIEMENT est introuvable."
  exit 1
fi

# Je place le projet sur la version taguée demandée.
git checkout "$TAG_DEPLOIEMENT"

# Le fichier d'environnement de production doit exister sur le VPS.
# Il contient des informations sensibles et ne doit pas être envoyé sur GitHub.
if [ ! -f "$FICHIER_ENV" ]; then
  echo "Le fichier $FICHIER_ENV est introuvable sur le VPS."
  exit 1
fi

# Le fichier Docker Compose de production doit exister dans le projet.
if [ ! -f "$FICHIER_COMPOSE" ]; then
  echo "Le fichier $FICHIER_COMPOSE est introuvable."
  exit 1
fi

# Je vérifie que la configuration Docker Compose est valide.
docker compose --env-file "$FICHIER_ENV" -f "$FICHIER_COMPOSE" config

# Je construis et relance les conteneurs de production.
relancer_conteneurs

# J'affiche l'état des conteneurs après le déploiement.
docker compose --env-file "$FICHIER_ENV" -f "$FICHIER_COMPOSE" ps

# Je vérifie que le site répond.
curl -fsSIL "$URL_PRODUCTION" >/dev/null

# Le déploiement est terminé, le retour arrière automatique n'est plus nécessaire.
trap - ERR

echo "Déploiement terminé avec succès."
