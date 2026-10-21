#!/usr/bin/env bash
# Régénère les données publiques du thème (résultats et tarifs) depuis config/*.json.
set -euo pipefail
cd "$(dirname "$0")/.."
php bin/sync-donnees.php
