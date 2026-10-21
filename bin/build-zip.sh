#!/usr/bin/env bash
# Fabrique les deux fichiers à installer dans WordPress :
#   releases/arvator-theme.zip  (Apparence > Thèmes > Ajouter > Téléverser)
#   releases/arvator-core.zip   (Extensions > Ajouter > Téléverser)
set -euo pipefail
cd "$(dirname "$0")/.."
php bin/sync-donnees.php >/dev/null
mkdir -p releases
rm -f releases/arvator-theme.zip releases/arvator-core.zip
( cd wp-content/themes  && zip -qr ../../releases/arvator-theme.zip arvator      -x "*.DS_Store" )
( cd wp-content/plugins && zip -qr ../../releases/arvator-core.zip  arvator-core -x "*.DS_Store" )
echo "Créés : releases/arvator-theme.zip et releases/arvator-core.zip"
