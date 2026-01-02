# Forex Signals — Laravel 12

Production-minded Laravel 12 application for **D1/W1/MN1 Forex market analysis**.

The system ingests **daily/weekly/monthly candlestick data** from Alpha Vantage, stores it with **idempotent imports**, exposes a clean **JSON API**, and renders an **MT4-like candlestick chart** using **TradingView Lightweight Charts** (Blade + vanilla JS). It also produces a **daily signal** (BUY/SELL/WAIT) with a structured explanation and sends a notification email.

This repository is intentionally scoped to **higher timeframes only (D1/W1/MN1)** to keep decision-making consistent and low-noise.

## Highlights
- Alpha Vantage candles ingestion (D1/W1/MN1) with retries, backoff, and idempotency
- MySQL persistence with unique constraints to prevent duplicates
- JSON API for candles and signal history
- Blade UI + TradingView Lightweight Charts (vanilla JS)
- Optional Volume histogram panel below price (FX volume may be missing; UI uses a simple activity proxy)
- Stochastic Oscillator + pragmatic Support/Resistance level detection
- Scheduler-first automation (single cron entry in production)
- Testable integration boundaries (HTTP fakes for Alpha Vantage/OpenAI)

## Tech Stack
- PHP 8.2+
- Laravel 12
- MySQL 8
- Blade + vanilla JavaScript
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
- `http://localhost/chart` (root `/` redirects to `/chart`)

Note: the chart intentionally shows **closed (EOD) candles only** (best for backtesting/AI consistency). The UI surfaces a `Last closed` date. Indicator controls are optional and live in a collapsible section below the chart; indicator panels stay aligned during zoom/pan.

In local/staging/testing, `/chart` includes a **Sync all timeframes** button that queues a background sync for **D1/W1/MN1** for the selected symbol. The UI polls status and shows per-timeframe progress.

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

API reference:
- Alpha Vantage FX Daily/Weekly documentation: https://www.alphavantage.co/documentation/#fx-daily

Mail:
- Configure `MAIL_*` variables (local default is Mailpit).

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

### Idempotency guarantees
Candles are imported using a unique key (`symbol_id`, `timeframe`, `t`) and upsert strategy, allowing safe re-runs and overlap-window refreshes.

### Rate-limit protection
Alpha Vantage calls are protected by:
- short-TTL response caching (`ALPHA_VANTAGE_CACHE_TTL_SECONDS`)
- per-symbol/timeframe sync locks to prevent concurrent imports (`ALPHA_VANTAGE_LOCK_TTL_SECONDS`)

## JSON API

- `GET /api/symbols`
- `GET /api/candles?symbol=EURUSD&timeframe=D1&from=YYYY-MM-DD&to=YYYY-MM-DD`
- `POST /api/sync-candles/all` (local/staging/testing only)
- `GET /api/sync-candles/status-all?symbol=EURUSD` (local/staging/testing only)
- `GET /api/overlays?symbol=EURUSD&timeframe=D1` (SR levels + Stochastic overlays)
- `GET /api/signals/latest?symbol=EURUSD&timeframe=D1`
- `GET /api/signals?symbol=EURUSD&timeframe=D1&from=YYYY-MM-DD&to=YYYY-MM-DD`

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
