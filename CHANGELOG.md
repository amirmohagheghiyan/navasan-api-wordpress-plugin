# Changelog

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versions follow [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-11-24

### Added
- **priceto.day as a second data source** ([margani/pricedb](https://github.com/margani/pricedb), MIT, data from tgju.org). It quotes in Rial; the conversion to Toman happens only inside its provider class. It supplies the daily high and low itself and the day change through `/v1/change`.
- **Provider layer**: a shared `One_Navasan_Provider` interface with one class per source (`One_Navasan_Provider_Priceto`, `One_Navasan_Provider_Navasan`). Parsing and validation stay WordPress independent and unit tested.
- **Rate source setting** (`priceto.day` / `Navasan-API` / `manual`) replacing "rate mode", and a **backup source** that is used only when the primary fails and its data is newer than the last healthy rate.
- Per-source base symbol (`gold-18k` for priceto.day with a symbol list loaded from `/v1/symbols`, `18ayar` for Navasan-API); the base karat is shared.
- Settings page: fields that do not belong to the selected source are dimmed; the status page shows which source the current rate came from (warning colour when it came from the backup); "Test connection" reports every path of both sources with a source column, the Toman rate and the data time.
- Market-hours aware stale-data warning (Saturday to Wednesday 08:00-22:00, Thursday 08:00-14:00, Friday closed, Tehran time) so nights, Fridays and Thursday afternoons do not raise it.
- Switching the source goes through the jump guard; the held rate says the cause is the source change.
- REST response gains a `source` field (`priceto`, `navasan` or `manual`). Shortcode, script and Dynamic Tag output are unchanged.
- One-time admin notice after upgrading from 1.0.x; upgrade routine and settings migrator.
- A refresh is scheduled shortly after the source settings change.
- `CHANGELOG.md`; `NOTICE`, README and the plugin header credit both projects.
- Tests: priceto.day parser, source selection (primary/backup/newer rule), market hours, settings migration, live checks for both sources, integration scenarios for sources and for upgrading from the `v1.0.0` tag (auto and manual sites).

### Changed
- Request timeout is 6 seconds (was 10). A path that fails with a network error, timeout, 5xx or 429 is skipped for 30 minutes (unless every path of the source is cooling down).
- Every request sends `User-Agent: One-Navasan-WordPress/<version> (+repository URL)`; a refresh contacts only as many paths as needed.
- The stored rate is read by the base symbol of the stored data, so changing the source in the settings never empties the rate before the next fetch.
- Plugin description now names both sources.

### Upgrade notes
- Settings, the last healthy rate, the log, the daily high/low, the pricing state and every product price are kept. `rate_mode=auto` becomes source "Navasan-API", `manual` becomes "manual"; the upgrade never changes the source by itself and the backup is "none".
- Slug, folder name, text domain, option and meta keys and the shortcode name are unchanged.

## [1.0.0] - 2026-11-24

### Added
- First release: live gold rate from Navasan-API (raw GitHub URL with GitHub Contents API and jsDelivr fallbacks and a per-URL connection test), manual rate and adjustment, jump and stale guards, currency conversion and rounding.
- `[one_gold_price]` shortcode, public REST route, light `data-one-gold` script, Elementor Dynamic Tags.
- WooCommerce pricing from weight, karat, wage, profit and tax with a mandatory preview before the first apply, chunked processing and background recalculation; works without WooCommerce (pricing stays off).
- Settings page in Persian, hooks, bilingual README, GPL-2.0-or-later license, attribution to Navasan-API.
