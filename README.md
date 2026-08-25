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

## Why a ledger instead of a stock column

It's the one design decision in this codebase worth calling out explicitly, because it's the
kind of trade-off an interviewer will ask about: a mutable `products.stock` column is faster
to read but has no history and is trivial to get out of sync (a missed update, a race between
two writers). Summing a movement ledger costs a bit more at read time (mitigated here with a
single aggregated subquery rather than N+1 lookups — see `ProductController::index`) but the
number is always correct by construction, and the ledger itself answers "what happened to this
SKU" for free.

## Tech stack

- Laravel 11, SQLite (ships in the repo — zero setup to run)
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

## Tests

```bash
php artisan test
```

Covers the pipeline's happy path, its skip/validation logic (missing SKU, unknown SKU, invalid
quantity, invalid date), SKU normalization, and the product listing/search endpoint.

## Roadmap (not built in this pass)

- Admin CRUD for products/suppliers instead of read-only views
- CSV upload through the UI instead of CLI-only
- A live deploy (Render/Fly.io) linked here
- GitHub Actions running `php artisan test` on push
