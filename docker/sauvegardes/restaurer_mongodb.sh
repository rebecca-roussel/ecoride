#!/usr/bin/env bash

set -euo pipefail

RACINE_PROJET="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${RACINE_PROJET}"

NOM_BASE_MONGODB="${MONGO_DB:-ecoride_journal}"

if [ "$#" -ne 1 ]; then
  echo "Usage : ./docker/sauvegardes/restaurer_mongodb.sh chemin/vers/sauvegarde.archive.gz" >&2
  exit 1
fi

FICHIER_SOURCE="$1"

if [ ! -f "${FICHIER_SOURCE}" ]; then
  echo "Fichier introuvable : ${FICHIER_SOURCE}" >&2
  exit 1
fi

docker compose exec -T mongodb sh -lc 'command -v mongorestore >/dev/null'

docker compose exec -T mongodb mongorestore \
  --archive \
  --gzip \
  --drop \
  --nsInclude="${NOM_BASE_MONGODB}.*" \
  < "${FICHIER_SOURCE}"

echo "Restauration MongoDB terminée depuis : ${FICHIER_SOURCE}"
