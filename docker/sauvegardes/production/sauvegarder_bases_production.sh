#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

RACINE_PROJET="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "${RACINE_PROJET}"

FICHIER_COMPOSE="docker-compose.production.yaml"
DOSSIER_SAUVEGARDES="/srv/ecoride/sauvegardes"
DOSSIER_POSTGRESQL="${DOSSIER_SAUVEGARDES}/postgresql"
DOSSIER_MONGODB="${DOSSIER_SAUVEGARDES}/mongodb"
DOSSIER_JOURNAUX="${DOSSIER_SAUVEGARDES}/journaux"

NOM_BASE_MONGODB="ecoride_journal"
DATE_SAUVEGARDE="$(date +%d-%m-%Y_%Hh%Mm%Ss)"
DUREE_CONSERVATION_JOURS="${1:-7}"

FICHIER_POSTGRESQL="${DOSSIER_POSTGRESQL}/ecoride_postgresql_${DATE_SAUVEGARDE}.sql"
FICHIER_MONGODB="${DOSSIER_MONGODB}/ecoride_mongodb_${NOM_BASE_MONGODB}_${DATE_SAUVEGARDE}.archive.gz"

if ! [[ "${DUREE_CONSERVATION_JOURS}" =~ ^[0-9]+$ ]]; then
  echo "Usage : ./docker/sauvegardes/production/sauvegarder_bases_production.sh nombre_de_jours" >&2
  exit 1
fi

if [ ! -f "${FICHIER_COMPOSE}" ]; then
  echo "Fichier Docker Compose de production introuvable : ${FICHIER_COMPOSE}" >&2
  exit 1
fi

mkdir -p "${DOSSIER_POSTGRESQL}" "${DOSSIER_MONGODB}" "${DOSSIER_JOURNAUX}"
chmod 700 "${DOSSIER_SAUVEGARDES}" "${DOSSIER_POSTGRESQL}" "${DOSSIER_MONGODB}" "${DOSSIER_JOURNAUX}"

echo "[$(date +'%d-%m-%Y %H:%M:%S')] Début de la sauvegarde production EcoRide"

docker compose -f "${FICHIER_COMPOSE}" exec -T postgresql sh -lc 'command -v pg_dump >/dev/null'
docker compose -f "${FICHIER_COMPOSE}" exec -T mongodb sh -lc 'command -v mongodump >/dev/null'

docker compose -f "${FICHIER_COMPOSE}" exec -T postgresql sh -lc '
  pg_dump \
    -U "$POSTGRES_USER" \
    -d "$POSTGRES_DB" \
    --clean \
    --if-exists \
    --no-owner \
    --no-privileges
' > "${FICHIER_POSTGRESQL}"

test -s "${FICHIER_POSTGRESQL}"

docker compose -f "${FICHIER_COMPOSE}" exec -T mongodb mongodump \
  --db "${NOM_BASE_MONGODB}" \
  --archive \
  --gzip \
  > "${FICHIER_MONGODB}"

test -s "${FICHIER_MONGODB}"

sha256sum "${FICHIER_POSTGRESQL}" > "${FICHIER_POSTGRESQL}.sha256"
sha256sum "${FICHIER_MONGODB}" > "${FICHIER_MONGODB}.sha256"

chmod 600 "${FICHIER_POSTGRESQL}" "${FICHIER_POSTGRESQL}.sha256" "${FICHIER_MONGODB}" "${FICHIER_MONGODB}.sha256"

find "${DOSSIER_POSTGRESQL}" -type f \
  \( -name "*.sql" -o -name "*.sql.sha256" \) \
  -mtime +"${DUREE_CONSERVATION_JOURS}" \
  -print \
  -delete

find "${DOSSIER_MONGODB}" -type f \
  \( -name "*.archive.gz" -o -name "*.archive.gz.sha256" \) \
  -mtime +"${DUREE_CONSERVATION_JOURS}" \
  -print \
  -delete

echo "[$(date +'%d-%m-%Y %H:%M:%S')] Sauvegarde production EcoRide terminée"
echo "PostgreSQL : ${FICHIER_POSTGRESQL}"
echo "MongoDB : ${FICHIER_MONGODB}"
echo "Conservation : ${DUREE_CONSERVATION_JOURS} jour(s)"
