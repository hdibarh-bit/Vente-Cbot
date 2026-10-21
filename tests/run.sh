#!/usr/bin/env bash
# Lance les tests d'intégration sur un WordPress JETABLE (jamais sur le vrai site).
# Usage : ARV_WP=/chemin/vers/wordpress ./tests/run.sh
set -euo pipefail
: "${ARV_WP:?Définir ARV_WP, le dossier du WordPress de test}"
PORT="${ARV_PORT:-8088}"
export ARV_URL="http://127.0.0.1:${PORT}"
export ARV_MAILLOG="${ARV_MAILLOG:-/tmp/arvator-mail.log}"
php -S "127.0.0.1:${PORT}" -t "$ARV_WP" >/tmp/arvator-test-server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 2
php "$(dirname "$0")/integration.php"
