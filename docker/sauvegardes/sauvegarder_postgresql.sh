#!/usr/bin/env bash

set -euo pipefail

RACINE_PROJET="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${RACINE_PROJET}"

DOSSIER_SORTIE="var/sauvegardes/postgresql"
DATE_SAUVEGARDE="$(date +%d-%m-%Y_%Hh%Mm%Ss)"
FICHIER_SORTIE="${DOSSIER_SORTIE}/ecoride_postgresql_${DATE_SAUVEGARDE}.sql"

mkdir -p "${DOSSIER_SORTIE}"

docker compose exec -T postgresql sh -lc '
  pg_dump \
    -U "$POSTGRES_USER" \
    -d "$POSTGRES_DB" \
    --clean \
    --if-exists \
    --no-owner \
    --no-privileges
' > "${FICHIER_SORTIE}"

test -s "${FICHIER_SORTIE}"

echo "Sauvegarde PostgreSQL créée : ${FICHIER_SORTIE}"
