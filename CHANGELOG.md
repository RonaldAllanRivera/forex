# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- JSON API endpoints for symbols and candles (`GET /api/symbols`, `GET /api/candles`).
- JSON API endpoint for overlays (`GET /api/overlays`) returning SR levels and Stochastic series.
- JSON API endpoints for signals (`GET /api/signals/latest`, `GET /api/signals`).
- AI Review endpoint (`POST /api/signals/review`) to generate/update the latest AI signal on-demand (local/staging/testing only).
- Lightweight Charts UI at `/chart`.
- Chart UI AI Signal panel that renders the latest AI reading and includes an **AI Review** button (no terminal commands required).
- Chart UI now surfaces `Last closed` date and explicitly indicates closed (EOD) candles only.
- Chart UI indicator controls (default OFF) for SR + Stochastic, including a second Stochastic panel.
- Monthly timeframe (MN1) support end-to-end (sync + API + chart selector).
- Optional Volume histogram panel below price.
- Synchronized zoom/pan across price + volume + stochastic panels.
- Candle sync status tracking (`candle_sync_statuses`) and API endpoints to sync all timeframes from `/chart`.
- Docker Compose `queue` service that runs `php artisan queue:work` for local development.
- OpenAI signal generation service and command (`forex:generate-signals`) backed by `OpenAiClient` and `SignalGeneratorService`.
- OpenAI configuration options (`OPENAI_MODEL`, `OPENAI_BASE_URL`, `OPENAI_TIMEOUT_SECONDS`).
- Daily email digest command (`forex:send-daily-email`) backed by `DailySignalsDigest` mailable and email view.
- Email recipients configuration via `FOREX_EMAIL_RECIPIENTS`.
- Scheduler wiring for the sync → signals → email pipeline (Laravel Scheduler).
- Health/status endpoint (`GET /api/health`) reporting last sync/signals/email timestamps.
- Optional local/testing default symbols seeding via `FOREX_SEED_DEFAULT_SYMBOLS`.

### Changed
- Switched candle ingestion provider from Finnhub to Alpha Vantage (FX_DAILY/FX_WEEKLY).
- Updated environment variables to use `ALPHA_VANTAGE_*` for API key and rate-limit protection.
- Updated candle sync/seeding and tests to use Alpha Vantage response format.
- Improved candle sync command output to show inserted/updated/unchanged/upserted and avoid no-op upserts.
- Widened `candles.timeframe` column to support `MN1`.
- Root route `/` now redirects to `/chart`.
- Chart indicator controls are now tucked into a collapsible section below the chart to reduce UI clutter.
- Indicator parameter inputs are disabled unless their indicator is enabled.
- Chart candle loading filters invalid/duplicate points before calling Lightweight Charts to avoid runtime errors.
- `/chart` sync action now uses a single **Sync all timeframes** button (D1/W1/MN1) and polls status via `status-all`.
- Widened `signals.timeframe` column to support `MN1` (forward migration for existing MySQL databases).

## [0.1.0] - 2025-12-28
### Added
- Laravel 12 application scaffold.
- Docker Compose local development stack (app + MySQL + Mailpit).
- Initial implementation plan (`PLAN.md`).
