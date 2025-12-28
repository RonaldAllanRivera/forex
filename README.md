# Forex Signals — Laravel 12

Production-minded Laravel 12 application for **D1/W1 Forex market analysis**.

The system ingests **daily/weekly candlestick data** from Finnhub, stores it with **idempotent imports**, exposes a clean **JSON API**, and renders an **MT4-like candlestick chart** using **TradingView Lightweight Charts** (Blade + vanilla JS). It also produces a **daily signal** (BUY/SELL/WAIT) with a structured explanation and sends a notification email.

This repository is intentionally scoped to **higher timeframes only (D1/W1)** to keep decision-making consistent and low-noise.

## Highlights
- Finnhub candles ingestion (D1/W1) with retries, backoff, and idempotency
- MySQL persistence with unique constraints to prevent duplicates
- JSON API for candles and signal history
- Blade UI + TradingView Lightweight Charts (vanilla JS)
- Stochastic Oscillator + pragmatic Support/Resistance level detection
- Scheduler-first automation (single cron entry in production)
- Testable integration boundaries (HTTP fakes for Finnhub/OpenAI)

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

### Tests
```bash
docker compose exec -T laravel.test php artisan test
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
- `FINNHUB_API_KEY`
- `OPENAI_API_KEY`

Mail:
- Configure `MAIL_*` variables (local default is Mailpit).

## Architecture (high level)

- **Domain**
  - `Symbol` — tracked instruments (e.g. EURUSD) mapped to provider symbols
  - `Candle` — OHLCV per symbol and timeframe (D1/W1)
  - `Signal` — daily/weekly stored outputs with explanation and metadata

- **Services**
  - `FinnhubClient` — HTTP client wrapper with retries and typed responses
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

## Project Plan
See `PLAN.md` for phased delivery milestones.

## Changelog
See `CHANGELOG.md`.
