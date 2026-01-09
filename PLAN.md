# Laravel Forex Candles + AI Signals (D1/W1/MN1) — Implementation Plan

## Scope
Build a Laravel 12 (PHP 8.2+) app that:

- Fetches **FREE** Forex candlestick data from **Alpha Vantage** (Daily + Weekly + Monthly)
- Stores candles in **MySQL** with **idempotent** imports
- Exposes a **JSON API** for candles and AI signals
- Renders an **MT4-like candlestick chart** using **TradingView Lightweight Charts** in **Blade** (vanilla JS)
- Generates a **daily** AI **BUY/SELL/WAIT** signal + explanation based on **price action**, **support/resistance**, and **Stochastic Oscillator**
- Sends an email notification (manual trading on XM; **no auto-execution**)

Your screenshots show profitable manual BUY trades on major pairs (e.g. USDJPY, EURUSD). This plan keeps analysis **low-stress** by focusing strictly on higher timeframes (**D1/W1/MN1**) decision-making with a simple, repeatable checklist.

---

## Non-Goals
- No intraday timeframes (no M15/H1/H4)
- No order execution / broker integration
- No SPA (Blade + vanilla JS only)
- No always-on workers required in production (queues can be managed by the host)

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
  - `forex:sync-candles` (idempotent, D1/W1/MN1)
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
  - `FX_MONTHLY`
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
- `timeframe` (enum: `D1`, `W1`, `MN1`)
- `t` (unix timestamp in seconds; open time)
- `o`, `h`, `l`, `c` (decimal)
- `v` (decimal or bigint; may be 0/nullable for forex)
- timestamps

**Unique constraint**: (`symbol_id`, `timeframe`, `t`) to guarantee idempotency.

### `signals`
- `id`
- `symbol_id`
- `timeframe` (enum: `D1`, `W1`, `MN1`)
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
  - Toggle timeframe: **D1/W1/MN1**
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
  - `timeframe`: `D1 | W1 | MN1`
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
  - Only D1/W1/MN1
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
  - `docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=MN1 --symbol=EURUSD`
  - `docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=D1 --symbol=USDJPY`
  - `docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=W1 --symbol=USDJPY`
  - `docker compose exec -T laravel.test php artisan forex:sync-candles --timeframe=MN1 --symbol=USDJPY`

The sync command reports `inserted`, `updated`, `unchanged`, and `upserted` to make overlap-window behavior explicit.

**Acceptance criteria**
- Running the command multiple times results in no duplicates
- Candles are stored for D1, W1, and MN1

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

## Phase 5 — Chart UI (Lightweight Charts) (COMPLETED)
- Blade page that:
  - fetches candle JSON
  - renders candlestick chart
  - switches between D1/W1/MN1
- Basic UI for selecting symbol/timeframe
- Default chart ranges (best-practice):
  - D1: last 2 years
  - W1: last 5 years
  - MN1: last 15 years
- Optional: user-selectable date range (from/to) to load more/less history

**Sync from Alpha Vantage (button on `/chart`, auth later)**
- Button on `/chart`: “Sync all timeframes”
- Scope: syncs **D1/W1/MN1** for the selected symbol
- Protected: only in local/staging, or behind auth/admin role (to be added later)
- Async: dispatch queued jobs (don’t block the UI)
- Rate-limited & locked: one sync per symbol+timeframe at a time
- UX: show per-timeframe status (`D1/W1/MN1`) + spinner while polling status
- Local DX: Docker Compose includes a dedicated `queue` service so the worker runs automatically

**Status**
- Implemented at `/chart` (root `/` redirects to `/chart`)
- Chart intentionally shows only **closed (EOD) candles** (no in-progress daily candle)
- D1/W1/MN1 time axis uses date-only display and surfaces `Last closed` date in the UI

**Acceptance criteria**
- Chart renders with correct timestamps and OHLC
- D1/W1/MN1 toggles work

---

## Phase 6 — Indicators + SR Levels (COMPLETED)
- Implement deterministic indicator computation from stored candles (not live API calls)
- Implement stochastic computation service
  - Defaults (best-practice for D1/W1): `K=14`, `D=3`, `smooth=3` with thresholds `20/80`
  - Must allow overriding params per request/UI (but default UI state is disabled)
- Implement SR detection service (swing highs/lows + clustering)
  - Defaults (best-practice): lookback window tuned per timeframe (D1 vs W1) and return a small set of strongest levels
- Add endpoint to return computed overlays (for chart + later for AI signals)
  - Example: `GET /api/overlays?symbol=EURUSD&timeframe=D1&stoch_k=14&stoch_d=3&stoch_smooth=3&sr_lookback=300`
  - Response should include:
    - Stochastic series (aligned to candle timestamps)
    - SR levels (price + strength/score)
- Update `/chart` to render overlays on the same page
  - Add enable/disable toggles for each overlay (default OFF)
  - Add controls for indicator parameters (with best-practice defaults for D1/W1)
  - When disabled, do not fetch/render the overlay

**Status**
- Implemented `/api/overlays` endpoint (SR + Stochastic)
- Chart renders SR horizontal lines + Stochastic in a second panel with toggles (default OFF)
- Added optional Volume histogram panel below price (FX volume may be missing; UI uses a simple activity proxy when unavailable)
- Indicator controls are organized in a collapsible section below the chart to reduce UI clutter
- Zoom/pan is synchronized across price + volume + stochastic panels so timelines stay aligned
- Tests added for overlays endpoint and indicator correctness

**Acceptance criteria**
- Stoch values match known sample calculations
- SR levels appear as reasonable horizontal lines on chart
- Chart allows toggling overlays on/off (default OFF)
- Chart allows changing indicator parameters and reloading overlays

---

## Phase 7 — OpenAI Signal Generation (COMPLETED)
- Implement `SignalGeneratorService`:
  - builds prompt payload from candles + stoch + SR
  - calls OpenAI
  - validates strict JSON output
  - stores to `signals` table
- Prompt guidance (rule-of-thumb hierarchy):
  1) Market structure + SR location (primary)
  2) Momentum confirmation (Stoch) (secondary)
  3) Risk framing (distance to SR, invalidation level)
- Use analysis windows (best-practice):
  - D1: last ~300 candles for patterns/context
  - W1: last ~260 candles for bias + major SR
  - MN1: last ~120 candles for macro regime + long-term SR context
  - Provide SR levels + indicator summaries to the model instead of sending multi-year raw OHLC
- Add artisan command: `forex:generate-signals`

**Status**
- Implemented `OpenAiClient` + `SignalGeneratorService`
- Added `forex:generate-signals` artisan command
- OpenAI config is controlled via env (`OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_BASE_URL`, `OPENAI_TIMEOUT_SECONDS`)
- Widened `signals.timeframe` to support `MN1` in MySQL (forward migration)

**Acceptance criteria**
- For each active symbol/timeframe, a daily signal is stored
- If OpenAI returns invalid JSON, the system retries then records a failure cleanly

---

## Phase 8 — Email Notifications (COMPLETED)
- Create Notification + Mailable view
- Add configuration for recipients
- Add artisan command: `forex:send-daily-email`

**Status**
- Added env-driven recipients config (`FOREX_EMAIL_RECIPIENTS`) via `config/forex.php`
- Added `DailySignalsDigest` mailable + Blade email view
- Added `forex:send-daily-email` artisan command
- Tests added using `Mail::fake()`

**Acceptance criteria**
- Daily email digest sends with latest signals
- Email sending failures are logged and retried appropriately

---

## Phase 8.5 — Show AI Signal on Chart UI (COMPLETED)
- On `/chart`, display the latest stored AI signal (“BUY/SELL/WAIT”) and explanation for the selected symbol + timeframe
- Data source: `GET /api/signals/latest?symbol=...&timeframe=...`
- Show:
  - signal, confidence, as-of date
  - AI reason/commentary
  - model used
  - key levels (from `levels_json`)
  - stochastic interpretation (from `stoch_json`)
- UX:
  - panel/card near the chart (keep it compact)
  - refresh when symbol/timeframe changes
  - clear “no signal yet” state with a call-to-action to click **AI Review**
  - add an **AI Review** button that generates/updates the latest signal without requiring terminal commands
    - `POST /api/signals/review`

**Acceptance criteria**
- Chart UI renders the latest signal panel correctly
- Panel updates when changing symbol/timeframe
- Reason text is readable and does not break the layout
- Clicking **AI Review** generates a signal for the selected symbol/timeframe and refreshes the panel

---

## Phase 9 — Scheduler + Operations (COMPLETED)
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

## Phase 10 — Hardening + Testing + Release (COMPLETED)
- Add full test coverage for critical paths
- Add optional local/testing seeder for default tracked pairs (env-gated)
- Make the entire site private with Laravel session login in all environments
  - Protect all web routes and `/api/*` routes with `auth`
  - Ensure POST requests to `/api/*` include CSRF token (API routes use `web` middleware)
- Add minimal admin bootstrap
  - `users.is_admin` flag
  - env-gated admin seeding for first login (`FOREX_SEED_ADMIN_USER`)
  - admin-only `/admin/settings` page for changing password
- Optional: TailwindCSS UI cleanup
  - Add TailwindCSS build pipeline (Vite) and move shared styles into `resources/css/app.css`
  - Introduce a shared Blade layout (and Blade components where helpful) to eliminate per-page `<style>` blocks
  - Migrate pages incrementally: login → admin settings → chart
  - Remove duplicated inline CSS only after each page is migrated
  - Note: chart panel heights are stabilized via `resources/css/app.css` (ensures consistent rendering regardless of Tailwind class generation)
- Add configuration docs:
  - env vars
  - symbol mapping rules
  - recommended cron times

**Acceptance criteria**
- Test suite passes
- A fresh deploy can be configured via env only
- Imports are idempotent and resilient to API hiccups

**Status**
- TailwindCSS migration completed for login, admin settings, and chart (shared layout + Vite assets)
- README updated with configuration docs (env vars, symbol mapping rules) and scheduler/cron guidance


---

## Phase 11 — Trade Management: Current Trade AI Review (COMPLETED)
- Add a DB-backed trade review feature for already-opened BUY/SELL trades
- UI: on `/chart`, add a **Current Trade AI Review** section
  - inputs: side (BUY/SELL), entry price, stop loss, optional take profit, optional opened-at date/time, optional notes
  - output: AI guidance to continue vs close/exit, stop placement quality, key levels, invalidation, and a simple management plan
- API:
  - `POST /api/trades/review` (creates a saved Trade + AI review snapshot)
  - `GET /api/trades` (list recent trade reviews)
  - `GET /api/trades/{id}` (view one review)
- Security/ops:
  - all endpoints require session auth; POST endpoints require CSRF token
  - keep rate limiting to protect API usage and cost
- Notes:
  - position size is intentionally not required (AI reviews trade quality/structure, not money management)
  - AI trade reviews use a dedicated prompt/JSON contract (separate from signals)
  - Persist model + prompt hash + raw response JSON for audit/debug; avoid storing the full prompt text unless explicitly needed
  - When pulling Phase 11 changes into a running environment, run DB migrations so `trades` / `trade_reviews` tables exist

**Status**
- Added `trades` + `trade_reviews` tables and Eloquent models.
- Implemented `TradeReviewGeneratorService` (candles + SR + stoch + OpenAI) and persisted review snapshots.
- Implemented admin-only API routes for trade review creation + listing.
- Added feature tests for the new trade review API endpoints.
- Added `/chart` UI panel for submitting trades and rendering structured AI results.
- Chart UX improvements: moved Trade Review panel below indicators, and added chart-click price picking for Entry/SL/TP fields.

**Acceptance criteria**
- Admin can submit a current trade (BUY/SELL) with entry + stop (and optional TP) and receive a structured review.
- Each trade review is persisted to the DB and can be retrieved via API.
- Trade review persistence includes AI output + model metadata + prompt hash (and raw response for troubleshooting).
- All endpoints require session auth; POST endpoints require CSRF token.

**Follow-ups (optional)**
- Render trade level overlays on the chart (Entry / SL / TP) and add show/hide controls.
- Add an admin UI to browse trade review history and optionally archive/soft-delete reviews.

---

## Best-practice suggestions
- Keep AI outputs on a strict JSON contract per use-case (signals vs trade reviews) and validate server-side.
- Persist only what you need for audit/debug:
  - store structured output + model + prompt hash + raw response JSON
  - avoid storing full prompts by default; if needed, gate behind an admin-only debug flag
- Treat chart overlays as UI state (show/hide) and keep DB mutations explicit and separate.
- For local development, prefer non-privileged per-project ports (via `.env` / Docker Compose) to avoid conflicts across multiple Laravel/Sail stacks.
- Prefer archive/soft-delete for admin data lifecycle actions instead of hard delete.
- Ensure new environments run `php artisan migrate` after pulling changes (especially when new tables are introduced).

