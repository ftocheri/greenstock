# GreenStock

A from-scratch demo of a nursery/garden-supply inventory system, built to show three things
end to end: a **Laravel ETL pipeline**, **database schema design**, and a **Vue 3 + Inertia
front end**.

> All data, code, and business logic here are original and fictional. This is a portfolio
> project, not derived from or containing any employer codebase.

## What it does

GreenStock tracks a plant nursery's product catalog and stock levels, and imports nightly
supplier restock feeds through a small, testable pipeline.

- **Products, categories, suppliers** — a normal relational catalog.
- **Inventory as a ledger, not a counter.** Stock isn't a mutable column on `products` — it's
  *derived* by summing an append-only `inventory_movements` table (`in` / `out` /
  `adjustment`). That means the current stock can never drift out of sync with the history
  that produced it, and the movement log itself is a useful audit trail (see any product's
  "Movement history" page).
- **A supplier feed pipeline.** `php artisan inventory:import path/to/feed.csv` runs each row
  of a CSV through five single-purpose stages, composed with Laravel's
  `Illuminate\Pipeline\Pipeline`:

  ```
  ValidateRow → NormalizeSku → UpsertProduct → RecordMovement → FlagLowStock
  ```

  Each stage lives in `app/Pipelines/Inventory/` and does exactly one job — shape validation,
  SKU cleanup (vendor prefixes, stray whitespace, casing), looking up the product (deliberately
  *not* auto-creating one for an unrecognized SKU — a restock feed shouldn't be able to invent
  new catalog items), recording the movement, and flagging anything still under its reorder
  threshold after the restock. The import runs as a queued job
  (`App\Jobs\ProcessInventoryFeedJob`) and is wired into the scheduler
  (`routes/console.php`) as a simulated nightly sync.
- **A dashboard and product catalog** built with Vue 3 + Inertia.js: stat tiles, a 6-month
  stock-movement chart, recent import runs, and a searchable/sortable product table.
- **An admin area for manual stock adjustments** (`/admin/inventory`), restricted to a single
  designated admin account rather than any logged-in user — see "Admin access" below.

## Why a ledger instead of a stock column

It's the one design decision in this codebase worth calling out explicitly, because it's the
kind of trade-off an interviewer will ask about: a mutable `products.stock` column is faster
to read but has no history and is trivial to get out of sync (a missed update, a race between
two writers). Summing a movement ledger costs a bit more at read time (mitigated here with a
single aggregated subquery rather than N+1 lookups — see `ProductController::index`) but the
number is always correct by construction, and the ledger itself answers "what happened to this
SKU" for free.

## Tech stack

- Laravel 12, SQLite locally / Postgres in production (ships with SQLite — zero setup to run)
- Vue 3 + Inertia.js + Tailwind CSS (via Laravel Breeze)
- Chart.js / vue-chartjs for the dashboard chart
- PHPUnit feature tests

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build
php artisan serve
```

Visit `http://localhost:8000`, register an account (any email/password), and you'll land on
the dashboard with ~150 seeded products, 6 months of movement history, and 80 sample orders.

The seeder also writes a ready-to-use `storage/app/feeds/sample-supplier-feed.csv` against
whatever SKUs it just generated (they're randomized per run), including a few deliberately
bad rows so the pipeline's validation is worth watching:

```bash
php artisan inventory:import storage/app/feeds/sample-supplier-feed.csv
```

Check the result on the dashboard's "Recent supplier feed imports" table, or:

```bash
php artisan tinker --execute="print_r(App\Models\InventoryImportLog::latest()->first()->toArray());"
```

**Note on the queue:** this repo ships with `QUEUE_CONNECTION=sync` so the import command runs
immediately with no extra setup — the point being demonstrated either way. Switching to
`database` or `redis` and running `php artisan queue:work` makes the same job process
asynchronously with no code changes, which is the actual point of using `ShouldQueue` here.

## Admin access

`/admin/inventory` lets one designated account record manual stock corrections (a signed
delta + a required reason, landing on the same `inventory_movements` ledger as everything
else — `type = 'adjustment'`). It's restricted to whichever user has `is_admin = true`, not
just "any logged-in user," since the live copy of this app is a public URL anyone can register
on.

There's no admin account baked into the seeder or the repo. Set `ADMIN_EMAIL` /
`ADMIN_PASSWORD` in your environment and run:

```bash
php artisan admin:ensure
```

This is idempotent — safe to re-run any time you want to rotate the password.

## Tests

```bash
php artisan test
```

Covers the pipeline's happy path, its skip/validation logic (missing SKU, unknown SKU, invalid
quantity, invalid date), SKU normalization, the product listing/search endpoint, and the admin
inventory endpoints (403 for non-admins, successful adjustment, validation).

## Live demo & deployment

The live copy runs on Render's free web service (compute) with a free Neon Postgres database
(data) — split deliberately, since Render's free tier has no persistent disk (a SQLite file
there would reset on every restart, and free-tier containers restart often — any 15-minute-idle
sleep is a fresh one). Neon's free Postgres is a separate managed service, so data survives
regardless of what Render's container does. Trade-off: a cold container takes ~30-60s to wake
after being idle.

**One-time setup:**

1. Create a free [Neon](https://neon.tech) project and copy its connection string.
2. On [Render](https://render.com), create a new Web Service from this repo — it picks up the
   `Dockerfile` automatically, or apply `render.yaml` directly as a Blueprint.
3. Set these environment variables on Render:
   - `DB_CONNECTION=pgsql`
   - `DB_URL` = Neon's **direct** (non-pooled) connection string — the hostname **without**
     `-pooler` in it (Laravel reads `DB_URL`, not the more common `DATABASE_URL` — see
     `.env.example`). Neon's pooled endpoint runs PgBouncer in transaction-pooling mode, which
     doesn't handle the multi-statement DDL that migrations run — confirmed directly: the
     pooled string failed partway through the very first migration with a cryptic "current
     transaction is aborted" error, while the direct string ran clean. Traffic here is low
     enough that pooling isn't needed anyway, so just use the direct string everywhere.
   - `APP_KEY` — generate one locally with `php artisan key:generate --show` and paste it in
   - `APP_URL` — the Render-assigned URL, once you have it
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `ADMIN_EMAIL` / `ADMIN_PASSWORD` — your choice; nothing admin-related is ever committed
4. After the first deploy, open Render's Shell tab once and run:
   ```bash
   php artisan db:seed --force
   php artisan admin:ensure
   ```
   (`--force` matters: Laravel's `db:seed` prompts for confirmation when `APP_ENV=production`,
   and that prompt silently cancels the command in a non-interactive shell.)
   Both are one-time. `docker-entrypoint.sh` runs `php artisan migrate --force` on every boot
   (safe/idempotent), but deliberately does **not** run `db:seed` automatically — the seeder
   isn't idempotent, so re-running it on every free-tier wake-from-sleep would keep appending
   duplicate products and movements forever.

From then on, pushing to `main` auto-redeploys and picks up any new migrations. Data persists
in Neon independent of whatever Render's container does.

## Roadmap (not built in this pass)

- Admin CRUD for products/suppliers/categories (this pass only covers stock adjustments)
- Order management (fulfill/cancel) from the admin area
- CSV upload through the UI instead of CLI-only
- GitHub Actions running `php artisan test` on push
