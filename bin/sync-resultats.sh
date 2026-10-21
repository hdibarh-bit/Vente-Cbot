#!/usr/bin/env bash
# Copie les chiffres de backtest (source unique : config/resultats-backtest.json) dans le thème.
set -euo pipefail
cd "$(dirname "$0")/.."
cp config/resultats-backtest.json wp-content/themes/arvator/data/resultats-backtest.json
echo "Résultats copiés dans le thème."
