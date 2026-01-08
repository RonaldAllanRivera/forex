# Forex Signals — Laravel 12

Production-minded **Laravel 12** application that showcases end-to-end delivery: secure authentication, resilient data ingestion, background job orchestration, testable service boundaries, and a responsive UI.

The system targets **D1/W1/MN1 Forex market analysis**. It ingests **daily/weekly/monthly candlestick data** from Alpha Vantage, persists it with **idempotent upserts**, exposes a clean **JSON API**, and renders an **MT4-like candlestick chart** using **TradingView Lightweight Charts** (Blade + vanilla JS). It also generates an AI-assisted **BUY/SELL/WAIT** signal with structured reasoning and sends a daily digest email.

Scope is intentionally constrained to **higher timeframes (D1/W1/MN1)** to reduce noise and keep decision-making consistent.

## Highlights
- Secure-by-default: **site-wide session auth** for web + `/api/*` routes, CSRF-protected POST API endpoints
- Minimal admin bootstrap (`users.is_admin`) with an admin-only password settings page
- Alpha Vantage candles ingestion (D1/W1/MN1) with retries, backoff, and idempotent imports
- MySQL persistence with unique constraints to prevent duplicates
- JSON API for candles, overlays, and signal history
- DB-backed **Current Trade AI Review** (admin-only) with persisted trade + AI review snapshots
- Blade UI + TradingView Lightweight Charts (vanilla JS)
- TailwindCSS via Vite + shared layout (incremental migration away from per-page inline CSS)
- Optional Volume histogram panel below price (FX volume may be missing; UI uses a simple activity proxy)
- Stochastic Oscillator + pragmatic Support/Resistance level detection
- Scheduler-first automation (single cron entry in production)
- Testable integration boundaries (HTTP fakes for Alpha Vantage/OpenAI)

## Tech Stack
- PHP 8.2+
- Laravel 12
- MySQL 8
- Blade + vanilla JavaScript
- TailwindCSS + Vite
- Docker Compose (local dev)

## Local Development (Docker Compose)

### Prerequisites
- Docker Desktop (Ubuntu)
- Docker Compose v2

### Start
```bash
docker compose up -d --build
```

The app is exposed on:
- `http://localhost` (port `80` by default)

Chart UI:
- `http://localhost/chart` (guests are redirected to `/login`)

Authentication:
- All web routes and `/api/*` routes require a logged-in user (Laravel session auth).
- POST requests to `/api/*` use the `web` middleware group and require a valid CSRF token.

Note: the chart intentionally shows **closed (EOD) candles only** (best for backtesting/AI consistency). The UI surfaces a `Last closed` date. Indicator controls are optional and live in a collapsible section below the chart; indicator panels stay aligned during zoom/pan.

On `/chart`, the **Sync all timeframes** button queues a background sync for **D1/W1/MN1** for the selected symbol. The UI polls status and shows per-timeframe progress.

The chart also includes an **AI Review** button that generates/updates the latest AI signal for the selected symbol + timeframe and renders the result directly in the AI panel (no terminal commands required).

Admin users also get a **Review current trade** panel to submit an already-open trade (entry/SL/TP) and receive a structured AI management review. Inputs are absolute prices; the UI supports chart-click price picking.

Mail testing is available at:
- Mailpit UI: `http://localhost:8025`

### Install dependencies / generate key (first run)
If you created the project with Composer, you already have an app key. Otherwise:

```bash
docker compose exec -T laravel.test composer install
docker compose exec -T laravel.test php artisan key:generate
```

### Database
Run migrations:

```bash
docker compose exec -T laravel.test php artisan migrate
```

Seed default symbols:

```bash
docker compose exec -T laravel.test php artisan db:seed
```

Sync candles:

```bash
docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=D1 --symbol=EURUSD
docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=W1 --symbol=EURUSD
docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=MN1 --symbol=EURUSD
docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=D1 --symbol=USDJPY
docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=W1 --symbol=USDJPY
docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=MN1 --symbol=USDJPY
```

The sync command reports `inserted`, `updated`, `unchanged`, and `upserted` to make overlap-window behavior explicit.

### Tests
```bash
docker compose exec -T laravel.test php artisan test
```

### Queue worker
This project uses `QUEUE_CONNECTION=database` by default.

For local development, Docker Compose starts a dedicated `queue` service that runs `php artisan queue:work` automatically. You typically only need:

```bash
docker compose up -d
```

### Stop
Keep containers (so you can see them in Docker Desktop):

```bash
docker compose stop
```

Remove containers + network:

```bash
docker compose down
```

## Configuration

Environment variables are stored in `.env` (not committed). Use `.env.example` as the template.

Required integrations:
- `ALPHA_VANTAGE_API_KEY`
- `OPENAI_API_KEY`

Alpha Vantage options:
- `ALPHA_VANTAGE_BASE_URL` (default: `https://www.alphavantage.co`)
- `ALPHA_VANTAGE_CACHE_TTL_SECONDS` (response caching to reduce calls)
- `ALPHA_VANTAGE_LOCK_TTL_SECONDS` (per-symbol/timeframe lock to prevent concurrent sync)

OpenAI options:
- `OPENAI_MODEL` (e.g. `gpt-4o-mini`, `gpt-4o`)
- `OPENAI_BASE_URL` (default: `https://api.openai.com/v1`)
- `OPENAI_TIMEOUT_SECONDS`

Generate AI signals:

```bash
docker compose exec -T laravel.test php artisan forex:generate-signals --timeframe=D1
docker compose exec -T laravel.test php artisan forex:generate-signals --timeframe=W1
docker compose exec -T laravel.test php artisan forex:generate-signals --timeframe=MN1
```

Target a single symbol:

```bash
docker compose exec -T laravel.test php artisan forex:generate-signals --symbol=EURUSD --timeframe=D1
```

AI Review (no terminal)

On `/chart`, click **AI Review** to generate an updated signal for the currently selected symbol/timeframe.

Note: AI Review runs per timeframe (D1/W1/MN1). It uses candle OHLC (including candlestick patterns), support/resistance levels, and stochastic context. Different timeframes can produce similar-looking outputs because the same analysis rubric is applied per timeframe.

API endpoint:
- `POST /api/signals/review` (requires session auth + CSRF)

Example:

```bash
curl -sS -X POST "http://localhost/api/signals/review" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"symbol":"EURUSD","timeframe":"W1"}' | jq
```

API reference:
- Alpha Vantage FX Daily/Weekly documentation: https://www.alphavantage.co/documentation/#fx-daily

Mail:
- Configure `MAIL_*` variables (local default is Mailpit).
- Configure `FOREX_EMAIL_RECIPIENTS` (comma-separated) to enable `forex:send-daily-email`.

### Symbol mapping rules
Symbols are stored in the `symbols` table and referenced everywhere by `code`.

- `symbols.code`
  - UI/API symbol identifier (example: `EURUSD`)
  - unique
- `symbols.provider`
  - currently expects `alphavantage`
- `symbols.provider_symbol`
  - provider-specific instrument mapping
  - for Alpha Vantage FX, format is `FROM/TO` (example: `EUR/USD`)
  - the sync service splits this into `from_symbol=EUR` and `to_symbol=USD`

If you add new pairs, ensure `provider_symbol` is valid and `is_active=true`.

Optional seeding (local/testing only):
- Set `FOREX_SEED_DEFAULT_SYMBOLS=true` to seed a small default set of tracked pairs (via `db:seed`).
- Set `FOREX_SEED_ADMIN_USER=true` to seed the initial admin user (via `db:seed`).

Admin settings:
- Visit `GET /admin/settings` to change the admin password (admin-only).
- The chart footer shows an **Admin Settings** link for admin users.
- If you enabled `FOREX_SEED_ADMIN_USER`, change the seeded password immediately.

Send daily email digest:

```bash
docker compose exec -T laravel.test php artisan forex:send-daily-email
```

## Architecture (high level)

- **Domain**
  - `Symbol` — tracked instruments (e.g. EURUSD) mapped to provider symbols
  - `Candle` — OHLCV per symbol and timeframe (D1/W1/MN1)
  - `Signal` — daily/weekly stored outputs with explanation and metadata

- **Services**
  - `AlphaVantageClient` — HTTP client wrapper with retries and typed responses
  - `CandleSyncService` — overlap-window sync + upserts (idempotent)
  - Indicator services — Stochastic + Support/Resistance computation
  - `SignalGeneratorService` — prompt assembly, JSON schema validation, persistence

- **Automation**
  - Scheduled commands:
    - `forex:sync-candles`
    - `forex:generate-signals`
    - `forex:send-daily-email`

## Operations

### Scheduler
In production, configure a single cron entry to run Laravel Scheduler:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

Default forex pipeline schedule (UTC):
- D1 sync: weekdays 23:10
- D1 AI signals: weekdays 23:30
- Daily email digest: weekdays 23:40
- W1 sync: Monday 23:15
- W1 AI signals: Monday 23:35
- MN1 sync: 1st of month 23:20
- MN1 AI signals: 1st of month 23:37

These defaults are defined in `routes/console.php` using `Schedule::command(...)` and can be adjusted to match your preferred market close/candle provider timing.

Health/status endpoint:
- `GET /api/health` returns last sync/signals/email timestamps (stored in cache by the scheduled commands).

### Idempotency guarantees
Candles are imported using a unique key (`symbol_id`, `timeframe`, `t`) and upsert strategy, allowing safe re-runs and overlap-window refreshes.

### Rate-limit protection
Alpha Vantage calls are protected by:
- short-TTL response caching (`ALPHA_VANTAGE_CACHE_TTL_SECONDS`)
- per-symbol/timeframe sync locks to prevent concurrent imports (`ALPHA_VANTAGE_LOCK_TTL_SECONDS`)

## JSON API

- `GET /api/symbols`
- `GET /api/candles?symbol=EURUSD&timeframe=D1&from=YYYY-MM-DD&to=YYYY-MM-DD`
- `POST /api/sync-candles/all` (requires session auth + CSRF)
- `GET /api/sync-candles/status-all?symbol=EURUSD`
- `GET /api/overlays?symbol=EURUSD&timeframe=D1` (SR levels + Stochastic overlays)
- `GET /api/signals/latest?symbol=EURUSD&timeframe=D1`
- `POST /api/signals/review` (requires session auth + CSRF)
- `GET /api/signals?symbol=EURUSD&timeframe=D1&from=YYYY-MM-DD&to=YYYY-MM-DD`
- `POST /api/trades/review` (requires session auth + CSRF; admin-only)
- `GET /api/trades` (admin-only)
- `GET /api/trades/{id}` (admin-only)
- `GET /api/health`

Responses include a `meta` block with the resolved `symbol` and `timeframe`.

Examples:

```bash
curl -sS "http://localhost/api/symbols" | jq
curl -sS "http://localhost/api/candles?symbol=EURUSD&timeframe=D1" | jq
curl -sS "http://localhost/api/candles?symbol=EURUSD&timeframe=D1&from=2025-01-01&to=2025-12-31" | jq
curl -sS "http://localhost/api/overlays?symbol=EURUSD&timeframe=D1" | jq
curl -sS "http://localhost/api/signals/latest?symbol=EURUSD&timeframe=D1" | jq
curl -sS "http://localhost/api/signals?symbol=EURUSD&timeframe=D1&from=2025-01-01&to=2025-12-31" | jq
```

## Project Plan
See `PLAN.md` for phased delivery milestones.

## Changelog
See `CHANGELOG.md`.
