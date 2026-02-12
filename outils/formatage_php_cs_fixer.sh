#!/usr/bin/env bash
set -euo pipefail

# Racine du projet = dossier parent de "outils"
racine_projet="$(cd "$(dirname "$0")/.." && pwd)"
cd "$racine_projet"

exec "$racine_projet/vendor/bin/php-cs-fixer" "$@"
