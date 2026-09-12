# Legacy GFS Validation Rules — Seed Data

Source-of-truth spreadsheets used by `php artisan validation:import-legacy-rules`
to populate the four GFS validation tables from operator data.

## Files

| File | Source | Content |
|---|---|---|
| `groups.xlsx` | Operator export from legacy GFS portal (2026-05-12) | 23 user grades × 30 coverage columns matrix |
| `rules.xlsx` | Operator export from legacy GFS portal (2026-05-12) | 197 validation rules with thresholds and action permissions |

## Schema

These files drive 4 tables:
- `tb_prvalidationrulemasters` — one row per rule (197 total)
- `tb_prvalidationruledetails` — one row per rule with operator + threshold (197 total)
- `tb_prvalidationrulegroupmasters` — one row per grade (23 total)
- `tb_prvalidationrulegroupdetails` — grade × coverage × rule matrix (~430 rows)

## How to refresh

When the operator team revises the rule set:

1. Export updated spreadsheets from the legacy GFS portal (or hand-edit existing copies)
2. Replace the two `.xlsx` files in this directory
3. Open a PR with the change
4. After merge, run:
   ```
   php artisan validation:import-legacy-rules --dry-run    # preview diff
   php artisan validation:import-legacy-rules --force      # apply
   ```
5. Importer is idempotent — re-runs are safe and reflect the latest spreadsheet exactly

## Validation expectations

- Every cell in the matrix must reference a `Rule Code` defined in `rules.xlsx`
- Every `Select Rule For` value must match an `alpharicvggroup.s_GroupName` (the importer warns on mismatches)
- Dates must be in `DD-MM-YYYY` or `YYYY-MM-DD` format
- Formula operators: `<`, `<=`, `==`, `=`, `!=`, `>`, `>=`
- Yes/No fields: `Yes` / `No` (case insensitive) → stored as `YES` / `NO`
- `Rule Apply On`: `Booking Date`, `Term Start Date`, `Transaction Start Date`

## Last refresh

| Date | By | Notes |
|---|---|---|
| 2026-05-12 | Operator team | Initial import: 23 grades, 197 rules, 423 matrix cells |
