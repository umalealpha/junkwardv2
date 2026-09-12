<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MotoLink (motolink.app) INBOUND assessment bridge
|--------------------------------------------------------------------------
|
| Ported 1:1 from the standalone Claims Tracker (js/motolink.js). A scheduled
| artisan command (claims:motolink-sync) pulls vehicle-damage assessments from
| motolink.app and mirrors them onto the matching Graphite claim.
|
| OFF / DARK BY DEFAULT. With no 'key' configured (and 'mock' off) the bridge
| is a COMPLETE NO-OP: the command returns immediately and NEVER makes an
| outbound HTTP call. Set MOTOLINK_API_BASE + MOTOLINK_API_KEY to enable.
|
*/

return [

    // Base URL of the motolink.app REST API, e.g. https://motolink.app/api
    // REQUIRED (with 'key') to enable the bridge. Trailing slashes are trimmed.
    'base' => env('MOTOLINK_API_BASE', ''),

    // Partner API key, sent as the `api-key` request header.
    // REQUIRED to enable. While this is blank the bridge is a no-op.
    'key' => env('MOTOLINK_API_KEY', ''),

    // Poll cadence in minutes (Claims Tracker default: 15). Informational /
    // future-use: the schedule entry runs every 15 min in the Kernel.
    'interval_minutes' => (int) env('MOTOLINK_SYNC_INTERVAL_MINUTES', 15),

    // Page size for the paginated /assessments pull.
    'page_size' => (int) env('MOTOLINK_PAGE_SIZE', 100),

    // Per-request HTTP timeout (seconds).
    'timeout' => (int) env('MOTOLINK_TIMEOUT', 20),

    // Use built-in fixtures instead of real HTTP (local testing ONLY).
    // Never enable in production. Default off.
    'mock' => filter_var(env('MOTOLINK_MOCK', false), FILTER_VALIDATE_BOOLEAN),
];
