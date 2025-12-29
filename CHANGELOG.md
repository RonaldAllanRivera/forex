# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- JSON API endpoints for symbols and candles (`GET /api/symbols`, `GET /api/candles`).
- JSON API endpoints for signals (`GET /api/signals/latest`, `GET /api/signals`).
- Lightweight Charts UI at `/chart`.

### Changed
- Switched candle ingestion provider from Finnhub to Alpha Vantage (FX_DAILY/FX_WEEKLY).
- Updated environment variables to use `ALPHA_VANTAGE_*` for API key and rate-limit protection.
- Updated candle sync/seeding and tests to use Alpha Vantage response format.
- Improved candle sync command output to show inserted/updated/unchanged/upserted and avoid no-op upserts.
- Default seeded symbols reduced to EURUSD and USDJPY.
- Root route `/` now redirects to `/chart`.

## [0.1.0] - 2025-12-28
### Added
- Laravel 12 application scaffold.
- Docker Compose local development stack (app + MySQL + Mailpit).
- Initial implementation plan (`PLAN.md`).
