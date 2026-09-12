<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'https://graphite.alphadirect.co.bw'),
        env('REACT_SPA_URL'),
        env('START_SPA_URL'), // start.alphadirect.co.bw — customer-facing React app
        // Hardcoded SPA origins — kept here so a missing env var on any
        // deployed host doesn't silently break CORS. Belt-and-braces
        // with the env-driven entries above. CORS matches origin
        // EXACTLY (scheme + host + port), so each variant lives on its
        // own line.
        'https://start-v2.alphadirect.co.bw',
        'https://start-dev2.alphadirect.co.bw', // start-v2 STAGING (start-fe-react deploy-staging.yml)
        'https://start.alphadirect.co.bw',
        'https://graphite.alphadirect.co.bw',
        'https://graphite-v2-fe.alphadirect.co.bw',
        // Marketing website — uploads career-application files directly
        // from the visitor's browser to /public/website-leads/files.
        'https://www.alphadirect.co.bw',
        'https://alphadirect.co.bw',
    ]),

    // Dev convenience: any localhost / 127.0.0.1 port is allowed when APP_ENV=local
    // so multiple Vite servers (admin :3000, start :5173) work without env churn.
    'allowed_origins_patterns' => env('APP_ENV') === 'local'
        ? ['/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/']
        : [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'api-key'],

    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 3600,

    // SPA uses Bearer tokens, not cookies — credentials flag not needed
    'supports_credentials' => false,

];
