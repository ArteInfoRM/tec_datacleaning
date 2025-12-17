# CHANGELOG

All notable changes for the `tec_datacleaning` module (Database Stats Cleaning).
Always review security notes before running destructive operations (TRUNCATE, DELETE).

## [Unreleased]
- Small improvements and minor bugfixes in progress.

## [1.0.4] - 2025-12-17
### Added
- Admin validation improvements: form now collects validation errors and only persists settings when all validations pass.
- `computeModuleSecureKey()` helper to compute or provide a persistent `secure_key` deterministically; the helper returns the sentinel `NOKEY` when no key is available.
- Safe-guard logging when legacy configuration is present: the module logs the presence of non-JSON configuration for selected tables and ignores it for safety.

### Changed
- Security: replaced all uses of `serialize()`/`unserialize()` with `json_encode()`/`json_decode()` for storing `TEC_DATACLEANIG_SELECTED_TABLES` (avoids unsafe deserialization of untrusted data).
- Admin form handling: the secure key posted by the admin is validated and only saved if the full form validation succeeds (prevents partial saves that previously produced both error and success messages).
- Selected tables persistence: stored as JSON; on read the code accepts only valid JSON and intentionally ignores legacy serialized values (admin must re-save in BO to migrate to JSON).
- Cron controller: updated to read selected tables from JSON (and to log/ignore legacy serialized values). The cron endpoint still accepts only `secure_key` for authentication.
- `uninstall()` now cleans up all module configuration keys (`MONTHS`, `BATCH_SIZE`, `SELECTED_TABLES`, `SECURE_KEY`) and attempts a best-effort unregister of `displayBackOfficeHeader` (errors are logged but do not block uninstall).
- Improved handling of helper form checkbox POST shapes: the admin UI accepts both array shapes and checkbox-keyed POSTs.

### Fixed
- Prevented the scenario where an invalid posted secure key was saved before other validation errors were detected (which produced confusing duplicate messages).
- Ensured the module does not unserialize legacy data automatically for safety; instead the admin is prompted (via logs and UI behavior) to re-save configuration to migrate.

### Security
- Avoided unsafe PHP deserialization (serialize/unserialize) for module configuration storage — migrated to JSON.
- The cron endpoint validation logic is centralized and more robust; when no usable key is available the API responds with a clear `NOKEY` notice and an appropriate HTTP error.
- Continued recommendation: protect the cron endpoint (IP whitelist, HTTPS, and/or additional access controls).

### Notes / Warnings
- Legacy configuration values saved with `serialize()` will be ignored by the new code for safety — to migrate, open the module configuration in Back Office, re-select the desired tables and save (this will persist them as JSON).
- Always execute `dry_run=1` and take a DB backup before running destructive operations (TRUNCATE or DELETE).

## [1.0.3] - 2025-12-17
### Added
- Advanced cron endpoint for automated cleaning: `module/tec_datacleaning/cron` (authenticated via `secure_key`).
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
- Resolved issues where the controller did not have a valid module instance: now uses `Module::getInstanceByName('tec_datacleaning')` as a reliable fallback and calls cleanup methods through that instance.

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
