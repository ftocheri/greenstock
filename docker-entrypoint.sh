#!/bin/sh
set -e

# Idempotent — safe to run on every boot, including every free-tier wake-from-sleep.
# Deliberately NOT running db:seed here (see README's "Live demo" section for why).
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
