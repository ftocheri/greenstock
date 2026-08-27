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
- **Natural-language product search** — "Ask about your inventory" on the products page
  translates a plain-English query into the same filters the manual search box already
  produces. See "Natural-language search" below for the design.

## Why a ledger instead of a stock column

It's the one design decision in this codebase worth calling out explicitly, because it's the
kind of trade-off an interviewer will ask about: a mutable `products.stock` column is faster
to read but has no history and is trivial to get out of sync (a missed update, a race between
two writers). Summing a movement ledger costs a bit more at read time (mitigated here with a
single aggregated subquery rather than N+1 lookups — see `ProductController::index`) but the
number is always correct by construction, and the ledger itself answers "what happened to this
SKU" for free.

## Natural-language search

Typing something like *"everything from Wisoky under 20 units"* into the "Ask about your
inventory" box on the products page sends that text to the Anthropic API
(`app/Services/InventoryQueryAssistant.php`), which translates it into a structured filter —
and that filter is applied through the *exact same* query-building code
(`ProductController::index`) the manual search box already uses. The design's whole safety
story is one sentence: **the model never gets more authority over the database than a human
typing URL query params already has.**

- **Forced, schema-constrained output.** The request forces a single tool call
  (`tool_choice`) with `strict: true` on the tool definition, so Anthropic enforces the
  filter's shape (types, enums, no extra fields) before the response even comes back — the
  model can return `{"supplier": "Wisoky", "max_stock": 20}`-shaped data or nothing at all, never
  free-form text and never SQL.
- **Re-validated anyway.** Strict mode doesn't enforce numeric ranges, so a hallucinated
  `min_stock: -50` is still clamped server-side, and `sort` is re-checked against the identical
  whitelist `ProductController::index` already uses for manual search — that whitelist, not
  request validation, is what actually prevents ORDER-BY injection, since column names can't be
  parameterized.
- **Grounded, not guessing.** The system prompt lists the real current supplier and category
  names (cached briefly) so the model matches against what actually exists instead of inventing
  a close-but-wrong name.
- **Provably no more authority than a human**, not just by design intent: `tests/Feature/ProductAiSearchTest.php`
  includes a prompt-injection-shaped test (a fake model response with a SQL-looking string as
  the `supplier` field) asserting zero matching rows and no SQL error — Eloquent's parameterized
  query builder treats it as inert data, never as code — plus an equivalence test proving an
  AI-translated filter and the identical hand-typed query params return the identical result
  set through the identical controller code.
- **Cheap and bounded on purpose.** Model is Haiku (`claude-haiku-4-5`, configurable via
  `ANTHROPIC_MODEL`) — fast and inexpensive for translating one short sentence. The route is
  throttled (`throttle:10,1`) and the query is capped at 200 characters, since this runs on a
  public demo against a real paid API key.
- **Fails closed and quietly.** No `ANTHROPIC_API_KEY` configured, a network hiccup, or a
  malformed response all produce the same friendly inline error — the rest of the app, including
  the manual search box right below it, is completely unaffected.

## Tech stack

- Laravel 12, SQLite locally / Postgres in production (ships with SQLite — zero setup to run)
- Vue 3 + Inertia.js + Tailwind CSS (via Laravel Breeze)
- Anthropic API (Claude Haiku) for natural-language product search
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
quantity, invalid date), SKU normalization, the product listing/search endpoint, the admin
inventory endpoints (403 for non-admins, successful adjustment, validation), and the AI search
endpoint (`Http::fake()`-stubbed — no real Anthropic calls in the suite): happy path, malformed/
missing tool response, connection failure, rate limiting, out-of-range and prompt-injection-shaped
values from the model, and the AI-vs-hand-typed equivalence test described above.

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
   - `ANTHROPIC_API_KEY` — from [console.anthropic.com](https://console.anthropic.com), powers
     natural-language product search. Optional in the sense that the rest of the app works fine
     without it — that one feature just shows a friendly error instead.
   - `ANTHROPIC_MODEL` — defaults to `claude-haiku-4-5-20251001` if unset; only needed if you
     want to point it at a different model
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

## CI

`.github/workflows/ci.yml` runs the PHPUnit suite on every push to `main`, then (on success)
notifies [the portfolio site](https://github.com/ftocheri/ftocheri.github.io) to rebuild, so a
change to `portfolio.json` here shows up there automatically — see that repo's README for how
the sync works.

## Roadmap (not built in this pass)

- Admin CRUD for products/suppliers/categories (this pass only covers stock adjustments)
- Order management (fulfill/cancel) from the admin area
- CSV upload through the UI instead of CLI-only
