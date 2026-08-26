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
exec php -S 0.0.0.0:"${PORT:-8080}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
