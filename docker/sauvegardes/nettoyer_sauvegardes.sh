#!/usr/bin/env bash

set -euo pipefail

RACINE_PROJET="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${RACINE_PROJET}"

DOSSIER_SAUVEGARDES="var/sauvegardes"
NB_JOURS_CONSERVATION="${1:-7}"

if ! [[ "${NB_JOURS_CONSERVATION}" =~ ^[0-9]+$ ]]; then
  echo "Usage : ./docker/sauvegardes/nettoyer_sauvegardes.sh nombre_de_jours" >&2
  exit 1
fi

if [ ! -d "${DOSSIER_SAUVEGARDES}" ]; then
  echo "Aucun dossier de sauvegardes trouvé : ${DOSSIER_SAUVEGARDES}"
  exit 0
fi

find "${DOSSIER_SAUVEGARDES}" -type f \
  \( -name "*.sql" -o -name "*.archive.gz" \) \
  -mtime +"${NB_JOURS_CONSERVATION}" \
  -print \
  -delete

echo "Nettoyage terminé. Durée de conservation locale : ${NB_JOURS_CONSERVATION} jour(s)."
