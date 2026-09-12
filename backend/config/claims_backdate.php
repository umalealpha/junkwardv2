<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claims backdate governance — master switch
    |--------------------------------------------------------------------------
    | Ported from the standalone Claims Tracker's backdate governance. The
    | ENFORCEMENT hook + the alerts are inert while this is off. The effective
    | state is the RUNTIME integration toggle `claims_backdate_governance`
    | (Admin > Integrations, IntegrationSettings::isEnabled(...)) which falls
    | back to this env default when there is no DB row.
    |
    | Default OFF — ships dark and additive. When OFF the claim date-edit path
    | (ClaimStageTimelineService::update) behaves EXACTLY as it does today: the
    | governance evaluate() call returns a no-op sentinel, nothing is validated,
    | no event is recorded and no alert is sent. Enforcement + alerts engage
    | ONLY when this flag is on.
    |
    | Admin management of grants/settings/requests is a PREVIEW surface — it is
    | available to admins regardless of this flag so they can pre-configure
    | before turning enforcement on. Only the validation hook + alerts are
    | flag-gated.
    */
    'enabled' => env('CLAIMS_BACKDATE_ENABLED', false),

    // Runtime-toggle key checked in IntegrationSettings::isEnabled().
    'integration_key' => 'claims_backdate_governance',

    /*
    | Timezone "today" / FY-start reckoning is done in. Botswana is UTC+2, no
    | DST. Matches the claims SLA engine.
    */
    'timezone' => env('CLAIMS_BACKDATE_TIMEZONE', 'Africa/Gaborone'),

    /*
    |--------------------------------------------------------------------------
    | Rules
    |--------------------------------------------------------------------------
    | fy_start_month — Botswana financial year starts 1 July. The hard floor:
    |   nothing may be backdated before the current FY start.
    | max_days_back — cap (in calendar days) on how far back a date may be set.
    |   Overridable at runtime from the settings endpoint (AppSetting key
    |   `backdate_max_days_back`); this is the default / fallback.
    */
    'fy_start_month'    => (int) env('CLAIMS_BACKDATE_FY_START_MONTH', 7),
    'max_days_back'     => (int) env('CLAIMS_BACKDATE_MAX_DAYS_BACK', 30),
    'max_days_back_min' => 1,
    'max_days_back_max' => 365,

    /*
    |--------------------------------------------------------------------------
    | RBAC (Spatie roles)
    |--------------------------------------------------------------------------
    | manage   — full control of the Backdate Control screen (grants, settings,
    |            events, decide requests) + an unconditional backdate override.
    | request  — may submit a backdate approval request, and (with an active
    |            grant) may backdate within the allowed window.
    | Everyone else is blocked from backdating when the flag is on.
    */
    'roles' => [
        'manage'  => array_values(array_filter(array_map('trim', explode(',',
            env('CLAIMS_BACKDATE_MANAGE_ROLES', 'Super Admin,Admin,admin,developer'))))),
        'request' => array_values(array_filter(array_map('trim', explode(',',
            env('CLAIMS_BACKDATE_REQUEST_ROLES', 'Claims Manager'))))),
    ],

    // Sentinel target for a grant that applies to ALL request-role users.
    'all_managers_token' => 'ALL_CLAIMS_MANAGERS',

    /*
    |--------------------------------------------------------------------------
    | Watched date fields
    |--------------------------------------------------------------------------
    | The claim_tracker_workflow date columns a change to (below "today") is a
    | backdate. Mirrors the tracker's CLAIM_DATE_FIELDS and
    | ClaimStageTimelineService::DATE_FIELDS.
    */
    'date_fields' => [
        'claim_docs_received', 'assessor_allotment_date', 'file_uploaded_to_gt',
        'physical_assessment', 'quote_request_date', 'quote_finalisation',
        'assessment_report_date', 'po_generation_date', 'po_issue_date',
        'parts_eta', 'parts_delivery_date', 'confirmation_date',
        'replacement_date', 'job_end_date',
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert settings (AppSetting keys) — all send-gated
    |--------------------------------------------------------------------------
    | No alert is ever sent unless the flag is ON *and* the relevant channel is
    | configured (a webhook URL / at least one recipient).
    */
    'settings_keys' => [
        'max_days_back'    => 'backdate_max_days_back',
        'teams_webhook'    => 'backdate_teams_webhook',
        'alert_recipients' => 'backdate_alert_recipients',
    ],
];
