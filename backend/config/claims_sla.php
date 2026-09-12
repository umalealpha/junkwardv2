<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claims SLA engine — master switch
    |--------------------------------------------------------------------------
    | The engine + its API are inert while this is off. The effective state is
    | the RUNTIME integration toggle `claims_sla` (Admin > Integrations,
    | IntegrationSettings::isEnabled('claims_sla')) which falls back to this env
    | default when there is no DB row. Default OFF — ships dark, additive, and
    | can be killed instantly with no migration revert. Mirrors the Help Desk
    | SLA + claims_automation runtime-toggle pattern already in this repo.
    |
    | Part of the Claims Tracker -> Graphite migration (Job 1, Phase 1). This
    | engine only READS claim data Graphite already holds (claim_tracker_workflow
    | + claims) — it never writes to live claim/financial tables.
    */
    'enabled' => env('CLAIMS_SLA_ENABLED', false),

    // Runtime-toggle key checked in IntegrationSettings::isEnabled().
    'integration_key' => 'claims_sla',

    /*
    | Timezone the working-day reckoning is done in. Botswana is UTC+2, no DST.
    | Reuses the same zone as the Help Desk SLA engine.
    */
    'timezone' => env('CLAIMS_SLA_TIMEZONE', 'Africa/Gaborone'),

    // Seconds the dashboard / leaderboard aggregate payloads are cached.
    'dashboard_cache_seconds' => (int) env('CLAIMS_SLA_DASHBOARD_CACHE_SECONDS', 60),

    /*
    | How many working days BEFORE a stage/overall due date the status flips
    | from "on_track" to "due_soon" (the amber warning band).
    */
    'due_soon_working_days' => (int) env('CLAIMS_SLA_DUE_SOON_DAYS', 1),

    /*
    |--------------------------------------------------------------------------
    | Per-weekday working-day WEIGHT (half-day support)
    |--------------------------------------------------------------------------
    | Which days are open at all is driven by the shared Help Desk business
    | calendar (help_desk_business_calendar.is_open). This map layers a
    | fractional WEIGHT on top of an open day — the fraction of a working day it
    | counts toward a deadline — WITHOUT touching that shared calendar (so the
    | Help Desk SLA engine is unaffected). ISO weekday: 1 = Mon ... 7 = Sun.
    |
    | The claims team works Saturday as a HALF day (confirmed 2026-08-17), so
    | Saturday (6) should weigh 0.5. It is env-gated and DEFAULTS TO 1.0 (a full
    | day = today's behaviour) so this ships INERT: set CLAIMS_SLA_SATURDAY_WEIGHT
    | to 0.5 per environment (staging first) to activate the half-day, bundled
    | with the corrected deadline matrix. Any open day not listed weighs 1.0.
    */
    'day_weights' => [
        6 => (float) env('CLAIMS_SLA_SATURDAY_WEIGHT', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | RBAC — the two claims roles from the migration plan (Spatie roles)
    |--------------------------------------------------------------------------
    | Claims Team  — may RECORD stage dates (PATCH the timeline).
    | Claims Manager — full access (record + read SLA dashboard/leaderboard).
    | Read of a single claim's timeline/SLA is allowed to either role.
    */
    'roles' => [
        'team'    => env('CLAIMS_SLA_ROLE_TEAM', 'Claims Team'),
        'manager' => env('CLAIMS_SLA_ROLE_MANAGER', 'Claims Manager'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA start anchor
    |--------------------------------------------------------------------------
    | The working-day clock starts at the first non-null of these, resolved in
    | order. claim_docs_received is the tracker's own anchor; the claim's
    | registered date is the fallback so a claim with no workflow row yet still
    | gets a computed SLA.
    */
    'start_anchor' => [
        'workflow_column' => 'claim_docs_received',
        // Claim model attributes tried in order for the fallback anchor.
        'claim_fallbacks' => ['registered_claim', 'created_at'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Claim-type -> SLA class resolution
    |--------------------------------------------------------------------------
    | claims.claim_type is coarse/free-ish, so we classify by case-insensitive
    | substring match into one of the matrix classes below. First match wins;
    | 'default_class' is used when nothing matches.
    |
    | NOTE (assumption to confirm with Aradhana/UW): the exact claim_type
    | strings in live data must be audited so every type lands on the right
    | class. These substrings are the safe first cut.
    |
    | ORDER IS LOAD-BEARING — first match wins, so the more specific needle must
    | come first. 'accidental' sits ABOVE 'accident' deliberately:
    | ACCIDENTALDAMAGE and 'Accidental Death' both contain 'accident' and would
    | otherwise be classed motor, which they are not. Plain 'Accident' does not
    | contain 'accidental', so it still falls through to motor.
    */
    'type_map' => [
        'glass'        => 'glass',
        'windscreen'   => 'glass',
        'lock'         => 'lock_and_key',
        'key'          => 'lock_and_key',
        // Must precede 'accident' — see the note above.
        'accidental'   => 'non_motor',
        // CONFIRMED BY THE CFO, 11-Aug-2026: claim_type 'Accident' IS a motor
        // accident. It is 1,208 of 2,950 sampled claims (41% of the book) and it
        // matched nothing here, so it fell through to the non_motor default and
        // was being measured against the WRONG deadline matrix. 136 of the 266
        // claims on the "NM: Overdue" list were these — over half the non-motor
        // overdue list was motor claims scored on non-motor rules, and none of
        // them received motor stage chips.
        'accident'     => 'motor',
        'motor'        => 'motor',
        'vehicle'      => 'motor',
    ],
    'default_class' => 'non_motor',

    /*
    |--------------------------------------------------------------------------
    | Deadline matrix (working days) — the tracker's proven Phase-1 values
    |--------------------------------------------------------------------------
    | Values are in WORKING DAYS (weekends + Help Desk holiday calendar
    | excluded). Each stage's `working_days` is CUMULATIVE from the start
    | anchor: the working-day offset by which that stage must be complete.
    | `key` is the claim_tracker_workflow column that stamps the stage done.
    |
    | Motor = 17 working days across 6 stages; Glass & Lock&Key = 2 working
    | days; Non-Motor = by sub-type. The per-stage split of Motor's 17 days is
    | an IT proposal (sums to 17) pending Aradhana/UW sign-off — see report.
    */
    'matrix' => [
        'motor' => [
            'label'              => 'Motor',
            'total_working_days' => 17,
            'stages' => [
                ['key' => 'assessor_allotment_date', 'label' => 'Assessor allotment & file upload', 'working_days' => 2],
                ['key' => 'physical_assessment',     'label' => 'Physical assessment',              'working_days' => 5],
                ['key' => 'quote_request_date',      'label' => 'Quote request',                    'working_days' => 7],
                ['key' => 'quote_finalisation',      'label' => 'Quote finalisation / report',      'working_days' => 10],
                ['key' => 'po_issue_date',           'label' => 'Purchase order issued',            'working_days' => 12],
                ['key' => 'job_end_date',            'label' => 'Job completed',                    'working_days' => 17],
            ],
        ],

        'glass' => [
            'label'              => 'Glass',
            'total_working_days' => 2,
            'stages' => [
                ['key' => 'job_end_date', 'label' => 'Glass replacement completed', 'working_days' => 2],
            ],
        ],

        'lock_and_key' => [
            'label'              => 'Lock & Key',
            'total_working_days' => 2,
            'stages' => [
                ['key' => 'job_end_date', 'label' => 'Lock & key resolved', 'working_days' => 2],
            ],
        ],

        /*
        | Non-Motor: the deadline depends on the sub-type (workflow
        | non_motor_sub_type). `sub_types` maps a case-insensitive sub-type
        | name to its total working days; `default_working_days` applies when
        | the sub-type is missing/unmapped. The sub_types list + their day
        | values MUST be filled in from the tracker by UW/Claims — the entries
        | below are placeholders derived from the tracker's known categories.
        */
        'non_motor' => [
            'label'                => 'Non-Motor',
            'default_working_days' => 10,
            'sub_types' => [
                // 'fire'        => 10,
                // 'theft'       => 10,
                // 'liability'   => 15,
                // 'money'       => 7,
                // 'all risks'   => 7,
            ],
            'stages' => [
                ['key' => 'assessment_report_date', 'label' => 'Assessment report', 'working_days' => null], // filled from total at runtime
                ['key' => 'job_end_date',           'label' => 'Claim finalised',   'working_days' => null],
            ],
        ],
    ],
];
