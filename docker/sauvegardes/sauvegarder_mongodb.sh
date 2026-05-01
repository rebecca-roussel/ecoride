#!/usr/bin/env bash

set -euo pipefail

RACINE_PROJET="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${RACINE_PROJET}"

NOM_BASE_MONGODB="${MONGO_DB:-ecoride_journal}"
DOSSIER_SORTIE="var/sauvegardes/mongodb"
DATE_SAUVEGARDE="$(date +%Y%m%d_%H%M%S)"
FICHIER_SORTIE="${DOSSIER_SORTIE}/ecoride_mongodb_${NOM_BASE_MONGODB}_${DATE_SAUVEGARDE}.archive.gz"

mkdir -p "${DOSSIER_SORTIE}"

docker compose exec -T mongodb sh -lc 'command -v mongodump >/dev/null'

docker compose exec -T mongodb mongodump \
  --db "${NOM_BASE_MONGODB}" \
  --archive \
  --gzip \
  > "${FICHIER_SORTIE}"

test -s "${FICHIER_SORTIE}"

echo "Sauvegarde MongoDB créée : ${FICHIER_SORTIE}"
