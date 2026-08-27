#!/bin/sh
set -e

# Idempotent — safe to run on every boot, including every free-tier wake-from-sleep.
# Deliberately NOT running db:seed here (see README's "Live demo" section for why).
php artisan migrate --force

# Deliberately NOT using `php artisan serve` — it spawns the actual PHP built-in
# server as a *child* process that does not reliably inherit custom env vars
# (confirmed directly: DB_CONNECTION/DB_URL silently fell back to config's sqlite
# default in that child, while every other invocation — tinker, `artisan migrate`,
# etc. — saw them correctly). Running the same router script directly as the
# foreground process sidesteps that indirection entirely.
#
# The router script resolves the public path via getcwd(), exactly like
# `artisan serve` relies on — so this has to run from public/, not the app root.
cd public

# php -S handles one request at a time by default. That was fine when every request was a fast
# local DB query, but the AI search feature makes a real blocking outbound HTTP call (to
# Anthropic) that can take a few seconds — during which a single-worker server queues every
# other request behind it, including Render's own health check. PHP_CLI_SERVER_WORKERS (7.4+)
# forks a small worker pool so one slow request doesn't stall the whole container.
export PHP_CLI_SERVER_WORKERS=4

exec php -S 0.0.0.0:"${PORT:-8080}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
