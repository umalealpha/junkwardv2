<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claims decision workflow — master switch
    |--------------------------------------------------------------------------
    | The decision API is inert for NON-ADMIN users while this is off. The
    | effective state is the RUNTIME integration toggle `claims_decision_workflow`
    | (Admin > Integrations, IntegrationSettings::isEnabled(...)) which falls back
    | to this env default when there is no DB row. Default OFF — ships dark,
    | additive, and can be killed instantly with no migration revert. Mirrors the
    | claims_sla / claims_automation runtime-toggle pattern already in this repo.
    |
    | Admin / Super Admin can PREVIEW the workflow (read + act) on staging and
    | prod while the flag is off; every other role only sees it once the flag is
    | on. This preview carve-out is what lets ops validate the module against real
    | claims before switching it on for the wider claims team.
    |
    | Part of the Claims Tracker -> Graphite migration. This layer is SEPARATE
    | from Graphite's own claim status / sub-status workflow — recording a
    | decision never changes claims.status or any financial table.
    */
    'enabled' => env('CLAIMS_DECISION_ENABLED', false),

    // Runtime-toggle key checked in IntegrationSettings::isEnabled().
    'integration_key' => 'claims_decision_workflow',

    /*
    |--------------------------------------------------------------------------
    | RBAC (Spatie roles / permissions)
    |--------------------------------------------------------------------------
    | admin           — Admin / Super Admin roles; always preview + full access.
    | act_permission  — the Spatie permission a decider must hold to record a
    |                   decision (mirrors every other claim write endpoint).
    | reverse_roles   — roles that may reverse a decision (admins always may).
    | read_roles      — roles that may READ the decision panel when the flag is
    |                   on (admins may read regardless of the flag).
    */
    'roles' => [
        'admin'         => ['Admin', 'Super Admin'],
        'manager'       => env('CLAIMS_DECISION_ROLE_MANAGER', 'Claims Manager'),
        'team'          => env('CLAIMS_DECISION_ROLE_TEAM', 'Claims Team'),
    ],

    // Permission required to APPROVE / REPUDIATE (in addition to a claims role).
    'act_permission' => env('CLAIMS_DECISION_ACT_PERMISSION', 'claim-edit'),
];
