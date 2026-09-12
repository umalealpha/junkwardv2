<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claims Notifications log + dashboard — master switch
    |--------------------------------------------------------------------------
    | The recorder + its read API are inert while this is off. The effective
    | state is the RUNTIME integration toggle `claims_notifications` (Admin >
    | Integrations, IntegrationSettings::isEnabled('claims_notifications')),
    | which falls back to this env default when there is no DB row. Default OFF
    | — ships completely dark, additive, killable instantly with no migration
    | revert. Mirrors the claims_sla / claims_automation runtime-toggle pattern.
    |
    | Part of the Claims Tracker -> Graphite migration. This feature only ADDS
    | logging of notifications Graphite already sends (event(SendSms) -> Infobip,
    | event(SendMail) -> Mailgun) plus a read-only admin dashboard. It never
    | initiates a send and never changes send behaviour.
    |
    | While OFF, the recorder writes NOTHING and every read endpoint 404s — so
    | the log stays empty until an admin arms it to preview on UAT / prod.
    */
    'enabled' => env('CLAIMS_NOTIFICATIONS_ENABLED', false),

    // Runtime-toggle key checked in IntegrationSettings::isEnabled().
    'integration_key' => 'claims_notifications',

    // Seconds the overview aggregate payload is cached (0 = no cache).
    'dashboard_cache_seconds' => (int) env('CLAIMS_NOTIFICATIONS_CACHE_SECONDS', 30),

    // Cap on the "recent sends" log page.
    'recent_limit' => (int) env('CLAIMS_NOTIFICATIONS_RECENT_LIMIT', 100),

    /*
    |--------------------------------------------------------------------------
    | RBAC
    |--------------------------------------------------------------------------
    | manager — full read of the dashboard + log (Admin / Super Admin).
    | viewer  — read-only (Claims Manager), same payloads.
    | The controller merges Admin + Super Admin into the manager set.
    */
    'roles' => [
        'manager' => env('CLAIMS_NOTIFICATIONS_ROLE_MANAGER', 'Claims Manager'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Send-path -> trigger_key / channel classification
    |--------------------------------------------------------------------------
    | Graphite dispatches claim SMS/email through the generic SendSms / SendMail
    | events. A send is treated as a CLAIM notification (and thus recorded) when
    | its extradata carries ANY of these markers:
    |   - a numeric `claim_id`
    |   - a `hook` / `source` / `trigger_key` whose value starts with 'claim'
    |   - an explicit `trigger_key`
    | trigger_key is resolved in this order, else 'unknown'.
    */
    'trigger_key_fields' => ['trigger_key', 'hook', 'source', 'type'],

    /*
    | Approximate per-SMS billable unit used for the cost roll-up when the
    | provider doesn't return a segment count. BWP. Email is treated as 0.
    | This is an ESTIMATE for the dashboard only — not a billing source.
    */
    'sms_cost_units' => (float) env('CLAIMS_NOTIFICATIONS_SMS_COST', 0.20),
];
