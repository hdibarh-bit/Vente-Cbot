#!/usr/bin/env bash
# Fabrique les fichiers à installer dans WordPress (Apparence > Thèmes > Ajouter, puis Extensions > Ajouter).
set -euo pipefail
cd "$(dirname "$0")/.."
./bin/sync-resultats.sh
mkdir -p dist
rm -f dist/arvator-theme.zip
( cd wp-content/themes && zip -qr ../../dist/arvator-theme.zip arvator -x "*.DS_Store" )
echo "Créé : dist/arvator-theme.zip"
