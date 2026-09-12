<?php

return [

    'graphite_url' => env('GRAPHITE_URL', null),
    'APP_STATUS' => env('APP_STATUS', null),
    'mail_driver' => env('MAIL_DRIVER', null),
    'mail_port' => env('MAIL_PORT', null),
    'mail_host' => env('MAIL_HOST', null),
    'mail_username' => env('MAIL_USERNAME', null),
    'mail_password' => env('MAIL_PASSWORD', null),
    'mail_encryption' => env('MAIL_ENCRYPTION', null),
    'aws_key' => env('AWS_ACCESS_KEY_ID', null),
    'aws_secret' => env('AWS_SECRET_ACCESS_KEY', null),

    /*
     * Show the policy cover-sheet OTP on the verification page instead of
     * relying on an SMS. Set ONLY on staging, so testers do not need a real
     * handset.
     *
     * Defaults to FALSE, so every environment that has not deliberately opted
     * in — production included — shows nothing. CoverSheetAccessService also
     * refuses when APP_STATUS says Production, so both conditions must pass:
     * an unset or misspelt APP_STATUS cannot leak a customer's code on its
     * own. See CoverSheetAccessService::mayShowCodeOnScreen().
     */
    'COVER_SHEET_SHOW_OTP' => env('COVER_SHEET_SHOW_OTP', false),

];