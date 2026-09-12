<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claims automation master switch — now a RUNTIME TOGGLE
    |--------------------------------------------------------------------------
    | The master on/off for the claims-automation pipeline (auto-email the
    | claim form on registration, document OCR + summary, exception triage) is
    | the runtime integration toggle `claims_automation`, flipped from
    | Admin > Integrations (IntegrationSettings::isEnabled('claims_automation')).
    | It defaults OFF, is audited, and is per-environment (staging/prod have
    | separate DBs). No env var and no redeploy needed to arm it.
    */

    /*
    | Step 1 — when a claim is registered, email the claimant the correct
    | claim form, chosen by claim_type from the `claim_type_forms` table.
    | This is a finer sub-switch (default on); the master toggle above must
    | also be on for a mail to go out.
    */
    'auto_email_form' => env('CLAIMS_AUTO_EMAIL_FORM', true),

    /*
    | Friendly sender name shown to the claimant.
    */
    'from_name' => env('CLAIMS_EMAIL_FROM_NAME', 'Alpha Direct Claims'),

    /*
    |--------------------------------------------------------------------------
    | Claim-form send button (CFO, 11-Aug-2026) — the FIRST step, deliberately
    |--------------------------------------------------------------------------
    | A claims handler presses a button on the claim, confirms the form and the
    | claimant's email address, and the claimant is sent a PRE-FILLED PDF plus a
    | no-password link to complete the same form online and upload documents.
    |
    | A human chooses, rather than the system guessing: `claim_type = Accident`
    | is 1,208 claims (41% of the book) and nothing in the data reliably says
    | whether those are motor accidents — resolveClass() defaults every
    | unmatched type to non_motor, and is_motor_claim reads 0 on every glass
    | claim. Automatic selection would send the wrong form to four claimants in
    | ten. Once Claims confirm the mapping, the same service can be fired
    | automatically from claim registration with no rewrite.
    |
    | Default OFF. The real switch is the runtime toggle `claims_form_dispatch`
    | in Admin > Integrations — audited, per-environment, no redeploy.
    */
    'form_dispatch_enabled' => env('CLAIMS_FORM_DISPATCH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Block claims on dead (cancelled/expired) policies — QA finding C4
    |--------------------------------------------------------------------------
    | When true, ClaimsV2Controller::store rejects a claim registered against a
    | cancelled (status 2) or expired (status 3) policy — a fraud/leakage guard.
    |
    | DEFAULT FALSE, deliberately. Live data (2026-07): ~23–30 claims/quarter are
    | currently registered against CANCELLED policies and processed (0 against
    | expired), and date_of_loss is frequently null at registration — so a blanket
    | block would stop an active flow and could reject legitimate in-cover-period
    | late claims. Enable (CLAIMS_BLOCK_DEAD_POLICY=true) only after Claims/Finance
    | validate the cases and confirm it should be enforced.
    */
    'block_dead_policy' => env('CLAIMS_BLOCK_DEAD_POLICY', false),

    /*
    |--------------------------------------------------------------------------
    | Scheduled executive KPI reports (Claims-Tracker migration, Phase 2)
    |--------------------------------------------------------------------------
    | The scheduled-report pipeline (claim_report_schedules + the
    | claims:run-report-schedules tick) is gated by the runtime integration
    | toggle `claims_scheduled_reports` (Admin > Integrations), default OFF.
    | With the flag OFF the scheduler still assembles + renders + logs each due
    | report but SENDS NOTHING; only flag-ON + an enabled schedule + explicit
    | recipients dispatches mail via Mailgun.
    |
    | 'major_claim_threshold' — a claim's net reserve at or above this (BWP) is
    | counted as a "major" claim in the KPI report. There is no per-claim
    | is_major flag in Graphite, so this is a DERIVED figure (reported as such).
    */
    'major_claim_threshold' => (float) env('CLAIMS_MAJOR_THRESHOLD', 100000),

    /*
    | Max rows accepted in a single bulk-claim import file (upload/preview and
    | commit). A guard against runaway spreadsheets; the commit path is also
    | rate-limited at the route + routes each row through the existing
    | claim-create path (validation intact). Bulk import is gated by the
    | runtime toggle `claims_bulk_import` (Admin > Integrations), default OFF —
    | preview/dry-run works for admins, but commit is refused while OFF.
    */
    'bulk_import_max_rows' => (int) env('CLAIMS_BULK_IMPORT_MAX_ROWS', 2000),

    /*
    |--------------------------------------------------------------------------
    | Premium confirmation (CFO, 11-Aug-2026) — Leg 2 of the claims flow
    |--------------------------------------------------------------------------
    | Raised automatically when a claim is registered, pre-filled from the
    | policy ledger. A paid-up policy releases itself with nobody involved; an
    | outstanding one ALWAYS reaches a person and is never auto-declined.
    |
    | This replaces a manual Excel sheet that is signed and then attached to a
    | task in Odoo. Of that sheet's twenty fields only two were ever Finance's.
    |
    | Default OFF. The real switch is the runtime toggle `premium_confirmation`
    | in Admin > Integrations — audited, per-environment, no redeploy.
    */
    'premium_confirmation_enabled' => env('CLAIMS_PREMIUM_CONFIRMATION_ENABLED', false),
];
