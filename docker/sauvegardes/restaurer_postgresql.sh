#!/usr/bin/env bash

set -euo pipefail

RACINE_PROJET="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${RACINE_PROJET}"

if [ "$#" -ne 1 ]; then
  echo "Usage : ./docker/sauvegardes/restaurer_postgresql.sh chemin/vers/sauvegarde.sql" >&2
  exit 1
fi

FICHIER_SOURCE="$1"

if [ ! -f "${FICHIER_SOURCE}" ]; then
  echo "Fichier introuvable : ${FICHIER_SOURCE}" >&2
  exit 1
fi

docker compose exec -T postgresql sh -lc '
  psql \
    -v ON_ERROR_STOP=1 \
    -U "$POSTGRES_USER" \
    -d "$POSTGRES_DB"
' < "${FICHIER_SOURCE}"

echo "Restauration PostgreSQL terminée depuis : ${FICHIER_SOURCE}"
