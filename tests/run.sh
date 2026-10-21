#!/usr/bin/env bash
# Lance les tests d'intégration sur un WordPress JETABLE (jamais sur le vrai site).
# Usage : ARV_WP=/chemin/vers/wordpress ./tests/run.sh
set -euo pipefail
: "${ARV_WP:?Définir ARV_WP, le dossier du WordPress de test}"
PORT="${ARV_PORT:-8088}"
export ARV_URL="http://127.0.0.1:${PORT}"
export ARV_MAILLOG="${ARV_MAILLOG:-/tmp/arvator-mail.log}"
export ARV_STRIPE_LOG="${ARV_STRIPE_LOG:-/tmp/arvator-stripe.log}"
export ARV_STRIPE_FIXTURES="${ARV_STRIPE_FIXTURES:-/tmp/arvator-stripe-fixtures.json}"
# Fausse API Stripe pour les tests (aucun appel réel à Stripe).
mkdir -p "$ARV_WP/wp-content/mu-plugins"
cp "$(dirname "$0")/mu-plugins/arvator-test-stripe.php" "$ARV_WP/wp-content/mu-plugins/"
php -S "127.0.0.1:${PORT}" -t "$ARV_WP" >/tmp/arvator-test-server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 2
php "$(dirname "$0")/integration.php"
