<?php

return [
    /*
    | Base URL used to build the "direct link to the ticket" in notification
    | emails: {ticket_url_base}/{ticketId}. Override per environment via
    | HELP_DESK_TICKET_URL_BASE (e.g. the prod front-end host).
    */
    'ticket_url_base' => env('HELP_DESK_TICKET_URL_BASE', 'https://graphite-v2-fe.alphadirect.co.bw/help-desk'),

    // From-address for Help Desk notification emails.
    'from_email' => env('HELP_DESK_FROM_EMAIL', 'insurance@alphadirect.co.bw'),
    'from_name'  => env('HELP_DESK_FROM_NAME', 'Alpha Direct Help Desk'),

    /*
    | Timezone the SLA business-hours engine reckons in. Botswana is UTC+2 with
    | no DST. All business-hours math is done in this zone; DB timestamps stay
    | in the app timezone.
    */
    'timezone' => env('HELP_DESK_TIMEZONE', 'Africa/Gaborone'),

    /*
    | SLA Management System (additive subsystem — see docs/help-desk-sla-design).
    | The whole subsystem is inert while `enabled` is false, so it can ship dark
    | and be killed instantly without a migration revert.
    */
    'sla' => [
        'enabled' => env('HELP_DESK_SLA_ENABLED', false),

        // One business day = this many business hours. Used to convert the
        // "N business days" matrix targets into business minutes at seed time.
        'business_day_hours' => (int) env('HELP_DESK_SLA_BUSINESS_DAY_HOURS', 9),

        // Consumption thresholds (% of target) at which warnings fire.
        'warning_thresholds' => [75, 90],

        // Always notified for warnings / breaches / digest, in addition to the
        // ticket assignee. Resolved to users by email where possible. Kept in
        // config (env-overridable) rather than hardcoded in code.
        'escalation_recipients' => [
            ['name' => 'Pramod Bisen',  'email' => env('HELP_DESK_SLA_ESCALATION_1', 'pbisen@theriskco.com')],
            ['name' => 'Lakshmi Anand', 'email' => env('HELP_DESK_SLA_ESCALATION_2', 'lanand@theriskco.com')],
        ],

        // Daily management digest send time, in the help desk timezone.
        'digest_time' => env('HELP_DESK_SLA_DIGEST_TIME', '08:00'),

        // How long (minutes) the assembled business calendar is cached.
        'calendar_cache_minutes' => (int) env('HELP_DESK_SLA_CALENDAR_CACHE_MINUTES', 60),

        // Roles allowed to see the SLA dashboard + reports (management view).
        // The per-ticket SLA panel is NOT gated by this — it's visible to
        // anyone who can view the ticket.
        'dashboard_roles' => ['Super Admin', 'Manager', 'Admin', 'COO', 'CFO'],

        // Seconds the dashboard aggregate payload is cached.
        'dashboard_cache_seconds' => (int) env('HELP_DESK_SLA_DASHBOARD_CACHE_SECONDS', 60),
    ],
];
