<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FNOL (First Notification of Loss) — master switch
    |--------------------------------------------------------------------------
    | The FNOL API + reminder command are inert while this is off. The effective
    | state is the RUNTIME integration toggle `claims_fnol` (Admin > Integrations,
    | IntegrationSettings::isEnabled('claims_fnol')) which falls back to this env
    | default when there is no DB row. Default OFF — ships dark, additive, and can
    | be killed instantly with no migration revert. Mirrors the claims_sla /
    | claims_scheduled_reports runtime-toggle pattern already in this repo.
    */
    'enabled' => env('CLAIMS_FNOL_ENABLED', false),

    // Runtime-toggle key checked in IntegrationSettings::isEnabled().
    'integration_key' => 'claims_fnol',

    /*
    |--------------------------------------------------------------------------
    | Documentation-reminder command — send arming
    |--------------------------------------------------------------------------
    | SECOND, INDEPENDENT gate on top of the `claims_fnol` flag, specific to the
    | claims:fnol-doc-reminders command. Even with the feature flag ON, the
    | reminder command sends NOTHING unless this is also true. Default false so
    | the command is completely inert (renders/logs a would-send count only)
    | until Claims explicitly arms outbound email. Env: CLAIMS_FNOL_REMINDERS_ARMED.
    */
    'reminders_armed' => env('CLAIMS_FNOL_REMINDERS_ARMED', false),

    /*
    | Reminder cadence + ceiling. A reminder is due when no reminder has been
    | sent yet, or the last one is older than `reminder_interval_days`. After
    | `max_reminders` sends the FNOL stops being chased (no further email).
    */
    'reminder_interval_days' => (int) env('CLAIMS_FNOL_REMINDER_INTERVAL_DAYS', 3),
    'max_reminders'          => (int) env('CLAIMS_FNOL_MAX_REMINDERS', 5),

    /*
    | Per-run send cap. On the first armed run the whole open backlog is "due"
    | at once (every FNOL has a null last_reminder_at) — this bounds how many
    | reminders a single tick sends so the first arm is a controlled batch, not
    | one blast. The backlog then drains over subsequent daily ticks. 0 = no cap.
    */
    'reminder_batch_limit'   => (int) env('CLAIMS_FNOL_REMINDER_BATCH_LIMIT', 25),

    /*
    | From-address used for reminder emails. Falls back to the app's default
    | Mailgun from-address (config('mail.from.address')) when unset.
    */
    'reminder_from'      => env('CLAIMS_FNOL_REMINDER_FROM'),
    'reminder_from_name' => env('CLAIMS_FNOL_REMINDER_FROM_NAME', 'Alpha Direct Claims'),
];
