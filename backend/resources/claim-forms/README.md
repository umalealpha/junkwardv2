# Claim forms — canonical blank PDFs

This directory is the **single source of truth** for Alpha Direct's blank claim
forms. 29 forms, one clean slug each (`motor-accident.pdf`, `hospital-cash-back.pdf`, …).
`manifest.json` maps each slug to its display title and the original filename it
was supplied under.

## Where they are used

- **Graphite claim-form send button** — `claim_type_forms.pdf_path` points at
  `ClaimForms/<slug>` in the file store. `claims:seed-form-library` writes those
  rows; `claims:publish-form-library` uploads these files to the store.
- **Omni claim-forms vault** — Omni holds its own copy for staff reference,
  loaded by the Omni `load_claim_forms` command from this same directory.

## Sources reconciled (newest wins)

| Batch | Date | Notes |
|---|---|---|
| July pack | Jul-2026 | 25 forms (`claimsdoc/claim-forms/Claim forms/`) |
| Claims team zip | 11-Aug-2026 | added Fire + Mobile 2022 |
| Claims team "Updated Claim Forms" | 12-Aug-2026 | added **Bonu Legal, Hospital Cash Back, Accidental Death** |

The undated Mobile form was superseded by the 2022 version and dropped.

## Still missing / held out

- **Life** (7 claims) — no dedicated form exists; awaiting a fallback decision.
- **Money** (11 claims) — needs a classification correction first (Fidelity
  separate from Burglary) before it can be mapped.

Resolved 12-Aug: **Stated Benefits** now maps to the Workmen's Compensation
(WCA) form (confirmed by the claims team), and Bonu Legal / Hospital Cash Back /
Accidental Death forms were supplied and mapped.

## Updating a form

Replace the file here (keep the slug), commit, then on the target environment run
`php artisan claims:publish-form-library`. Do not rename slugs — the seeder and
both apps reference them.
