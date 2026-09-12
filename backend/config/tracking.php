<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Request Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Controls which requests are tracked in MongoDB for observability.
    | Modify these settings to include/exclude specific routes and methods.
    |
    */

    'enabled' => env('API_TRACKING_ENABLED', true),

    // MongoDB connection
    'mongodb_host' => env('MONGODB_HOST', '127.0.0.1'),
    'mongodb_port' => env('MONGODB_PORT', 27017),
    'mongodb_database' => env('MONGODB_DATABASE', 'graphite_observability'),

    // Only track these HTTP methods by default
    'methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],

    // Routes to ALWAYS track (even if GET) — supports wildcards
    'always_track' => [
        'api/v1/auth/login',
        'api/v1/auth/logout',
        'api/v1/auth/sso/*',
        'api/v1/reconciliation/*',
        'api/v1/ai/*',
        'auth/microsoft/*',
    ],

    // Routes to NEVER track — supports wildcards
    'ignore' => [
        'api/v1/health',
        'api/v1/lookups/*',
        'api/v1/products',
        'api/v1/products/*/plans',
        'api/v1/vehicle/*',
        'favicon.ico',
        '_debugbar/*',
        'logsViewerData',
        'livewire/*',
    ],

    // Sensitive fields to redact from stored request/response body
    'redact_fields' => [
        'password',
        'password_confirmation',
        'credit_card',
        'card_number',
        'cvv',
        'token',
        'secret',
        'api_key',
        'DB_PASSWORD',
    ],

    // Max response body size to store (bytes) — larger responses are truncated
    'max_response_size' => 10000,

    // Track outgoing HTTP calls (DPO, Realpay, Infobip, etc.)
    'track_outgoing' => env('API_TRACKING_OUTGOING', true),

    // Outgoing hosts to track — only these external calls are logged
    'outgoing_hosts' => [
        'secure.3gdirectpay.com',       // DPO
        'api.realpay.co.bw',            // Realpay
        'api2.infobip.com',             // Infobip SMS
        'graph.microsoft.com',          // Microsoft Graph / SSO
        'api.groq.com',                 // AI (Groq)
        'api.anthropic.com',            // AI (Claude)
        'login.microsoftonline.com',    // Microsoft OAuth
        'api.opensanctions.org',        // AML Screening
    ],
];
