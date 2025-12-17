# CHANGELOG

All notable changes for the `tec_datacleanig` module (Database Stats Cleaning).
Always review security notes before running destructive operations (TRUNCATE, DELETE).

## [Unreleased]
- Small improvements and minor bugfixes in progress.

## [1.0.3] - 2025-12-17
### Added
- Advanced cron endpoint for automated cleaning: `module/tec_datacleanig/cron` (authenticated via `secure_key`).
- Support for `truncate=1` to quickly empty selected statistic tables.
- `dry_run=1` mode for `truncate` and for cleaning operations: returns counts without modifying the database.
- Automatic fallback: if `TRUNCATE` is not allowed (permissions or FK constraints), the controller performs a batched `DELETE` (configurable with `batch_size`) until the table is emptied or a safety limit is reached.
- Added `ps_pagenotfound` to the managed tables (date column: `date_add`).
- New admin template `views/templates/admin/cron_instructions.tpl` with instructions and examples for cron, dry-run and truncate.

### Changed
- Admin UI: deterministic `secure_key` is displayed as a read-only value on the configuration form.
- `getAllowedTables()` is now public so the cron controller can safely retrieve the allowed tables list.
- Cron controller computes the expected `secure_key` robustly (md5(_COOKIE_KEY_ + moduleName) with a `Tools::encrypt` fallback) and accepts only the `secure_key` parameter (no `token`).
- `ps_log` is always excluded from truncate operations to avoid accidental loss of logs.

### Fixed
- Removed debug logging that exposed the `secure_key` in plain text from the logs.
- Resolved issues where the controller did not have a valid module instance: now uses `Module::getInstanceByName('tec_datacleanig')` as a reliable fallback and calls cleanup methods through that instance.

### Security
- The module no longer logs the `secure_key` to system logs.
- Recommendation: protect the cron endpoint (IP whitelist, HTTPS, additional authentication) and do not expose the `secure_key` publicly.

### Notes / Warnings
- Always run `dry_run` first and take a database backup before executing `truncate=1` — this operation is destructive.
- `TRUNCATE` requires appropriate database privileges; the batched `DELETE` fallback is used when `TRUNCATE` is not possible.
- On InnoDB with foreign-key constraints, `TRUNCATE` may fail; the `DELETE` fallback may also be blocked if constraints prevent deletion.

## Upgrade / Migration notes
- If you have external scripts calling the cron endpoint using the `token` parameter, update them to use `secure_key` (this module exclusively expects `secure_key`).
- If your database user lacks `TRUNCATE` privileges and you still need to empty tables, the module will now fall back to a batched `DELETE` (configure `batch_size` appropriately).

## Previous versions
- v1.0.2 — previous changes not included in this file.

---
