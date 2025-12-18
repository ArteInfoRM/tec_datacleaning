# Database Stats Cleaning

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
![Built for PrestaShop](https://img.shields.io/badge/Built%20for-PrestaShop-DF0067?logo=prestashop&logoColor=white)

`tec_datacleaning` is a PrestaShop module to safely clean and maintain database tables that contain website statistics (connections, page views, guest records, 404 pages, searches, etc.). It provides an automated cron endpoint, a fast TRUNCATE option, and a safe batched DELETE fallback when TRUNCATE cannot be executed.

> Important: TRUNCATE and DELETE are destructive operations. Always run `dry_run` first and take a full database backup before performing destructive operations.

---

## Key features

- Automated cron endpoint for scheduled cleaning: `/module/tec_datacleaning/cron` (authenticated via `secure_key`).
- `truncate=1` fast-clean option to empty selected statistic tables.
- `dry_run=1` mode for both truncate and clean operations to preview how many rows would be removed without changing the database.
- Batched `DELETE` fallback if `TRUNCATE` is not allowed (permission issues or foreign-key constraints) — configurable batch size.
- Default managed tables include `connections`, `connections_page`, `connections_source`, `guest`, `statssearch`, `pagenotfound` and `log` (log is always skipped for truncate operations).
- Admin UI shows a deterministic `secure_key` and a configuration form to select which tables to include and set the batch size.

---

## Requirements

- PrestaShop 1.7+ (module tested in PS 1.7 / 8 / 9 environments).
- The DB user must have appropriate privileges for `DELETE` operations. `TRUNCATE` requires elevated privileges and can be blocked by FK constraints.

---

## Installation

1. Upload the `tec_datacleaning` module folder to your PrestaShop `modules/` directory.
2. Install the module from the PrestaShop Back Office (Modules -> Module Manager).
3. Open the module configuration page and review the pre-selected tables. Adjust the `batch_size` and the number of months to keep as needed.

Notes:
- By default the module pre-selects a safe list of statistic tables. The `ps_log` table is intentionally skipped for truncation to avoid losing log history.

---

## Configuration (Back Office)

- `Module secure key`: a deterministic key shown on the form (may be computed by the module or read from configuration). Use this value in cron URLs.
- `How long to keep the data`: choose how many months of data to retain for cleaning operations.
- `Tables to clean`: checkbox list of allowed statistic tables — pick the ones you want included in automated cleaning.
- `Batch size`: number of rows processed per batch when performing batched DELETE operations.

Notes about secure_key and the form
- The module attempts to compute a deterministic secure key (based on `_COOKIE_KEY_` and the module name) when no explicit key is stored. If no key can be computed the module returns the sentinel value `NOKEY` and the cron endpoint will refuse requests until a valid key is configured in the BO.
- When upgrading from older versions: previously the module used PHP `serialize()` to store selected tables. For security, the module now stores selected tables as JSON. If legacy serialized data exists, the module intentionally ignores it and logs a message — please open the module configuration and re-save the table selection to migrate the stored value to JSON.

---

## Cron / Endpoint usage

The cron endpoint is:

```
http(s)://<your-shop>/module/tec_datacleaning/cron?secure_key=<SECURE_KEY>
```

Replace `<SECURE_KEY>` with the value shown in the module configuration (read-only or the value you set in BO).

### Example: Dry-run (safe)

This command returns the counts of rows that would be removed (no changes):

```bash
curl -s "https://example.com/module/tec_datacleaning/cron?secure_key=YOUR_SECURE_KEY&dry_run=1" | jq .
```

### Example: Run cleaning (non-destructive deletes in batches)

```bash
curl -s "https://example.com/module/tec_datacleaning/cron?secure_key=YOUR_SECURE_KEY&batch_size=1000" | jq .
```

### Truncate examples (fast cleanup, destructive)

Dry-run for truncate (reports rows that would be removed):

```bash
curl -s "https://example.com/module/tec_datacleaning/cron?secure_key=YOUR_SECURE_KEY&truncate=1&dry_run=1" | jq .
```

Real truncate (DESTRUCTIVE — empties selected tables, except `ps_log`):

```bash
curl -s "https://example.com/module/tec_datacleaning/cron?secure_key=YOUR_SECURE_KEY&truncate=1" | jq .
```

---

## secure_key: how it is generated (CLI)

You can compute the same deterministic secure key on the server by running (from your PrestaShop root):

```bash
php -r "require 'config/config.inc.php'; echo md5(_COOKIE_KEY_ . 'tec_datacleaning') . PHP_EOL;"
```

If the module configuration contains a user-provided value (`TEC_DATACLEANIG_SECURE_KEY`) that value is used in preference to the computed one.

If the module cannot compute or read any valid key it will return the `NOKEY` sentinel and the cron endpoint will respond with a clear error. In that case open the module configuration and provide a valid secure key.

---

## Behaviour details

- Authentication: the cron endpoint requires `secure_key` as the authentication parameter.
- `dry_run=1`: returns statistics (counts) and does not perform any DELETE/TRUNCATE.
- `truncate=1`: attempts a fast `TRUNCATE TABLE` for each selected table (skips `ps_log`). If `TRUNCATE` fails or is not permitted, the module attempts a batched `DELETE` using the configured `batch_size` until the table is empty or a safety iteration limit is reached.
- `batch_size` applies to batched DELETE operations (not to TRUNCATE).

---

## Safety & recommendations

- Always perform a `dry_run` first and keep a recent database backup before executing destructive operations.
- Prefer running truncate only during a low-traffic window.
- If your database uses InnoDB and has foreign-key constraints, `TRUNCATE` may fail: the module will attempt a batched `DELETE` but FK constraints can still block deletion. Review constraints before running destructive operations.
- Consider limiting access to the cron URL (IP whitelist or VPN access) to reduce the risk of unauthorized calls.

---

## Troubleshooting

- If you receive `Invalid token` in the JSON response, verify that `secure_key` matches the computed or configured value.
- If TRUNCATE fails and the fallback `DELETE` reports zero deleted rows, examine DB permissions and foreign-key constraints. Check server error logs for DB error messages.
- If you see permission errors, ensure your DB user has `DELETE` privileges. For TRUNCATE, database-level privileges may be required.

---

## Changelog

See `CHANGELOG.md` in the module folder for release notes and details about recent changes (version 1.0.4 contains security fixes and the migration to JSON storage for selected tables).

---

## Support

For support and further customization contact the module author or your technical partner. If you provide logs and a description of steps to reproduce, troubleshooting will be faster.

---

## License

Refer to the `LICENSE` file included in the module for license terms and usage rules.
