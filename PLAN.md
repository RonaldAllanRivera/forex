# Laravel Forex Candles + AI Signals (D1/W1) — Implementation Plan

## Scope
Build a Laravel 12 (PHP 8.2+) app that:

- Fetches **FREE** Forex candlestick data from **Alpha Vantage** (Daily + Weekly only)
- Stores candles in **MySQL** with **idempotent** imports
- Exposes a **JSON API** for candles and AI signals
- Renders an **MT4-like candlestick chart** using **TradingView Lightweight Charts** in **Blade** (vanilla JS)
- Generates a **daily** AI **BUY/SELL/WAIT** signal + explanation based on **price action**, **support/resistance**, and **Stochastic Oscillator**
- Sends an email notification (manual trading on XM; **no auto-execution**)

Your screenshots show profitable manual BUY trades on major pairs (e.g. USDJPY, EURUSD). This plan keeps analysis **low-stress** by focusing strictly on **D1/W1** decision-making with a simple, repeatable checklist.

---

## Non-Goals
- No intraday timeframes (no M15/H1/H4)
- No order execution / broker integration
- No SPA (Blade + vanilla JS only)
- No always-on workers required (queues optional)

---

## Key Requirements & Constraints
- **Laravel 12**, **PHP 8.2+**, **MySQL**
- **Siteground**-friendly deployment
- **Scheduler** driven with **one cron** entry
- API keys via env:
  - `ALPHA_VANTAGE_API_KEY`
  - `OPENAI_API_KEY`
  - `MAIL_*`
- Production-ready:
  - retries/backoff
  - idempotent imports
  - caching
  - error handling
  - observability
  - tests

---

## High-level Architecture
- **Domain**
  - `Symbol` (pairs you track)
  - `Candle` (OHLCV per symbol + timeframe)
  - `Signal` (daily AI output per symbol + timeframe + date)
- **Integrations**
  - `AlphaVantageClient` (HTTP)
  - `OpenAiClient` (HTTP)
- **Jobs/Commands** (Scheduler)
  - `forex:sync-candles` (idempotent, D1/W1)
  - `forex:generate-signals` (per symbol + timeframe)
  - `forex:send-daily-email` (digest)
- **UI**
  - Blade page that loads candles via JSON API and renders Lightweight Charts
  - Overlay support/resistance levels + show stochastic panel (simple canvas/SVG or second lightweight chart)

---

## Data Source Notes (Alpha Vantage)
- Use Alpha Vantage FX time series endpoints:
  - `FX_DAILY`
  - `FX_WEEKLY`
- Store symbols in `symbols.provider_symbol` as `FROM/TO` (e.g. `EUR/USD`, `USD/JPY`).
- Use `outputsize=compact` for recent ranges, `outputsize=full` for backfills.

---

## Data Model (Proposed)
### `symbols`
- `id`
- `code` (e.g. `EURUSD`)
- `provider` (e.g. `alphavantage`)
- `provider_symbol` (e.g. `EUR/USD`)
- `is_active` (bool)
- timestamps

### `candles`
- `id`
- `symbol_id`
- `timeframe` (enum: `D1`, `W1`)
- `t` (unix timestamp in seconds; open time)
- `o`, `h`, `l`, `c` (decimal)
- `v` (decimal or bigint; may be 0/nullable for forex)
- timestamps

**Unique constraint**: (`symbol_id`, `timeframe`, `t`) to guarantee idempotency.

### `signals`
- `id`
- `symbol_id`
- `timeframe` (enum: `D1`, `W1`)
- `as_of_date` (date; “signal date”)
- `signal` (enum: `BUY`, `SELL`, `WAIT`)
- `confidence` (tinyint 0-100, optional)
- `reason` (text)
- `levels_json` (json; support/resistance candidates)
- `stoch_json` (json; %K/%D latest + overbought/oversold)
- `prompt_hash` (string; for traceability)
- `model` (string)
- `raw_response_json` (json)
- timestamps

**Unique constraint**: (`symbol_id`, `timeframe`, `as_of_date`).

---

## API (Proposed)
Base: `/api`

- `GET /api/symbols`
  - List active symbols

- `GET /api/candles?symbol=EURUSD&timeframe=D1&from=...&to=...`
  - Returns sorted candles for charting

- `GET /api/signals/latest?symbol=EURUSD&timeframe=D1`
  - Returns the latest stored AI signal + computed indicators

- `GET /api/signals?symbol=EURUSD&timeframe=D1&from=...&to=...`
  - History for review/backtesting

**API concerns**
- Use `FormRequest` validation
- Use API Resources for consistent JSON
- Add `Cache-Control` for read endpoints

---

## Frontend (Blade + Vanilla JS)
- Route: `/chart/{symbol?}/{timeframe?}` (default symbol/timeframe)
- Use TradingView Lightweight Charts:
  - Self-host via npm build (preferred) OR CDN fallback
- Page features:
  - Candlestick chart
  - Toggle timeframe: **D1/W1 only**
  - Overlays:
    - horizontal lines for computed support/resistance levels
  - Sidebar panel:
    - latest AI signal
    - explanation
    - latest Stochastic values (%K/%D) and state (overbought/oversold)

---

## Indicator + Support/Resistance Computation (Server-side)
### Stochastic Oscillator
- Default parameters:
  - %K period: 14
  - %D smoothing: 3
- Compute from stored candles per symbol/timeframe.

### Support/Resistance (Pragmatic)
- Start with a deterministic, simple approach that works on D1/W1:
  - Identify recent swing highs/lows (fractals) over a rolling window (e.g. last 100 candles)
  - Cluster nearby levels using a price tolerance (e.g. ATR-based tolerance or fixed pip threshold by pair)
  - Return top N levels closest to current price + strongest touches

These levels are used to *guide* the AI narrative, not to automate entries.

---

## OpenAI Signal Generation (Best-Practice Prompting)
### Output contract
- Enforce strict JSON output:
  - `signal`: `BUY | SELL | WAIT`
  - `timeframe`: `D1 | W1`
  - `bias`: `bullish | bearish | neutral`
  - `key_levels`: array of levels with type `support|resistance`
  - `setup`: candlestick/price-action description
  - `stochastic`: interpretation (overbought/oversold/cross/divergence if detected)
  - `invalidation`: what would invalidate the idea
  - `stress_free_plan`: “what to wait for” if WAIT
  - `risk_note`: always present (non-advice disclaimer)

### Prompt ingredients (per symbol/timeframe)
- Last N candles (e.g. 120) as OHLC
- Computed stoch values (%K/%D, last ~30)
- Computed SR levels
- Constraints:
  - Only D1/W1
  - Favor clean, high-quality setups near SR
  - Prefer WAIT when unclear
  - No leverage advice, no position sizing, no guaranteed outcomes

### Model + reliability
- Use small, cost-effective models where possible.
- Add:
  - timeout
  - retry with exponential backoff
  - response schema validation
  - store raw response for auditing

---

## Email Notifications
- Daily digest (per user-configurable hour):
  - Latest D1 signals for tracked symbols
  - Weekly signal updates on the first trading day after weekly close
- Mail configuration via `MAIL_*` env vars.
- Use Laravel Notifications (Mail channel).

---

## Scheduling (Single Cron)
- Siteground cron:
  - `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`

Scheduled tasks (examples):
- `forex:sync-candles` runs once daily after market day close (configurable)
- `forex:generate-signals` runs after sync
- `forex:send-daily-email` runs after signals

If queues are enabled later:
- use `database` queue and schedule `queue:work --stop-when-empty` to avoid always-on workers.

---

## Caching, Retries, Idempotency, Hardening
- **Idempotent candle imports**:
  - use unique constraint + `upsert`
  - fetch overlapping windows (e.g. last 30 days) to correct revisions
- **Retries**:
  - Laravel HTTP client `retry()` with backoff and jitter
- **Rate limiting**:
  - cache Alpha Vantage responses with a short TTL to reduce repeated external calls
    - make TTL configurable (e.g. `ALPHA_VANTAGE_CACHE_TTL_SECONDS`)
    - allow disabling cache for debugging by setting TTL to `0`
  - use per-symbol/timeframe locks to prevent concurrent sync jobs from calling Alpha Vantage in parallel
    - make lock TTL configurable (e.g. `ALPHA_VANTAGE_LOCK_TTL_SECONDS`)
  - cache *your own* JSON API responses (candles/signal reads) with `Cache-Control` + server-side cache where appropriate
- **Observability**:
  - structured logs for imports and AI generation
  - store last successful sync timestamps
  - surface health status in an admin page or `/health` route

---

## Testing Strategy
- **Unit tests**
  - stochastic computation
  - support/resistance detection
  - candle upsert/idempotency rules

- **Feature tests**
  - API endpoints return correct schema
  - chart page loads

- **Integration tests (HTTP fakes)**
  - Alpha Vantage client: successful response, rate limit, timeouts
  - OpenAI client: valid JSON, invalid JSON, retries

- **Database tests**
  - unique constraints
  - signal uniqueness per date

---

# Phases

## Phase 1 — Project Bootstrap (COMPLETED)
- Local dev environment: **Ubuntu + Docker Desktop** using `docker compose`
- Create Laravel 12 project configured for PHP 8.2+
- Add `docker-compose.yml` (Sail-style) for local development:
  - `laravel.test` (PHP runtime + web server)
  - `mysql` (MySQL 8)
  - `mailpit` (local email testing)
- Standard commands:
  - `docker compose up -d --build`
  - `docker compose exec -T laravel.test php artisan migrate`
  - `docker compose exec -T laravel.test php artisan test`
  - `docker compose stop` (keep containers)
  - `docker compose down` (remove containers)
- Access:
  - Use `http://localhost` by default.
  - If you want `http://forex.test`, add `127.0.0.1 forex.test` to `/etc/hosts` and keep `APP_URL` consistent.
- Configure `.env.example` with:
  - `ALPHA_VANTAGE_API_KEY`, `OPENAI_API_KEY`
  - `DB_*`, `APP_URL`
  - `MAIL_*` (Mailpit defaults for local)
- Hardening basics:
  - Ensure `.env` files are not committed (gitignore all `.env*` except `.env.example`).
  - Add baseline docs (`README.md`, `CHANGELOG.md`).

**Acceptance criteria**
- `docker compose up -d --build` brings up app + mysql successfully
- `docker compose ps` shows containers running and `mysql` becomes healthy
- Migrations run successfully inside the container
- Home page renders from the containerized app (`http://localhost`)
- `php artisan test` passes inside the container
- `.env.example` is sufficient to onboard a fresh dev environment

---

## Phase 2 — Database + Domain Models (COMPLETED)
- Create migrations:
  - `symbols`, `candles`, `signals`
- Add Eloquent models + relationships
- Add enum/value objects for `Timeframe` (`D1`, `W1`)

**Acceptance criteria**
- Can create symbols and candles
- Unique constraints enforce idempotency

---

## Phase 3 — Alpha Vantage Integration + Candle Ingestion (COMPLETED)
- Implement `AlphaVantageClient` using Laravel HTTP client
  - Add short-TTL response caching to reduce API calls and avoid rate limits (configurable)
    - `ALPHA_VANTAGE_CACHE_TTL_SECONDS`
- Implement `CandleSyncService`:
  - backfill support (e.g. “last 2 years D1”, “last 5 years W1”)
  - incremental sync (daily)
  - overlap window re-sync (correct revisions)
  - Prevent concurrent syncs per symbol/timeframe using cache locks (configurable)
    - `ALPHA_VANTAGE_LOCK_TTL_SECONDS`
- Add artisan command: `forex:sync-candles`
  - Requires `ALPHA_VANTAGE_API_KEY` and at least one active row in `symbols`
  - Example: `docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=D1`

**Operational notes**
- Reset local DB and seed default symbols:
  - `docker compose exec -T laravel.test php artisan migrate:fresh --seed`
- Sync example:
  - `docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=D1 --symbol=EURUSD`
  - `docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=W1 --symbol=EURUSD`

**Acceptance criteria**
- Running the command multiple times results in no duplicates
- Candles are stored for D1 and W1

---

## Phase 4 — JSON API (COMPLETED)
- Implement endpoints:
  - symbols
  - candles
  - latest signal
  - signal history

**Endpoints**
- `GET /api/symbols`
- `GET /api/candles?symbol=EURUSD&timeframe=D1&from=YYYY-MM-DD&to=YYYY-MM-DD`

- `GET /api/signals/latest?symbol=EURUSD&timeframe=D1`
- `GET /api/signals?symbol=EURUSD&timeframe=D1&from=YYYY-MM-DD&to=YYYY-MM-DD`

**Status**
- Implemented: `symbols`, `candles`, `latest signal`, `signal history`
- Add caching for read endpoints:
  - Server-side cache for common reads (candles ranges, latest signal)
  - Add `Cache-Control` headers to allow browser/proxy caching where safe
  - Consider `ETag`/conditional requests for large candle payloads
  - Ensure caches are keyed by `symbol`, `timeframe`, and date range

**Acceptance criteria**
- API returns correct JSON for chart consumption
- Validation errors return consistent responses
- `GET /api/symbols` returns active symbols only
- `GET /api/candles` validates `symbol` + `timeframe` and returns candles sorted by `t`

---

## Phase 5 — Chart UI (Lightweight Charts)
- Blade page that:
  - fetches candle JSON
  - renders candlestick chart
  - switches between D1/W1
- Basic UI for selecting symbol/timeframe
- Default chart ranges (best-practice):
  - D1: last 2 years
  - W1: last 5 years
- Optional: user-selectable date range (from/to) to load more/less history

**Acceptance criteria**
- Chart renders with correct timestamps and OHLC
- D1/W1 toggles work

---

## Phase 6 — Indicators + SR Levels
- Implement stochastic computation service
- Implement SR detection service (swing highs/lows + clustering)
- Add endpoint to return computed overlays (or embed in signal response)
- Prefer deterministic SR/indicator computation from stored candles (not live API calls)

**Acceptance criteria**
- Stoch values match known sample calculations
- SR levels appear as reasonable horizontal lines on chart

---

## Phase 7 — OpenAI Signal Generation
- Implement `SignalGeneratorService`:
  - builds prompt payload from candles + stoch + SR
  - calls OpenAI
  - validates strict JSON output
  - stores to `signals` table
- Use analysis windows (best-practice):
  - D1: last ~300 candles for patterns/context
  - W1: last ~260 candles for bias + major SR
  - Provide SR levels + indicator summaries to the model instead of sending multi-year raw OHLC
- Add artisan command: `forex:generate-signals`

**Acceptance criteria**
- For each active symbol/timeframe, a daily signal is stored
- If OpenAI returns invalid JSON, the system retries then records a failure cleanly

---

## Phase 8 — Email Notifications
- Create Notification + Mailable view
- Add configuration for recipients
- Add artisan command: `forex:send-daily-email`

**Acceptance criteria**
- Daily email digest sends with latest signals
- Email sending failures are logged and retried appropriately

---

## Phase 9 — Scheduler + Operations
- Register scheduled commands in `routes/console.php` or scheduler kernel
- Document the single cron entry for Siteground
- Add health/status surface:
  - last sync time
  - last signal generation time
  - last email time

**Acceptance criteria**
- `schedule:run` triggers the pipeline end-to-end
- Status screen reflects job outcomes

---

## Phase 10 — Hardening + Testing + Release
- Add full test coverage for critical paths
- Add seeders for sample symbols (EURUSD, USDJPY, etc.)
- Add configuration docs:
  - env vars
  - symbol mapping rules
  - recommended cron times

**Acceptance criteria**
- Test suite passes
- A fresh deploy can be configured via env only
- Imports are idempotent and resilient to API hiccups
