<?php

/**
 * This is config for Infobip SMS.
 *
 * @link https://dev.infobip.com/send-sms/single-sms-message
 */
return [
    // Credentials are env-only (no hardcoded secret defaults) - matches the
    // fix/infobip-creds-env-only change on config/infobip.php.
    'from' => env('INFOBIP_FROM'),
    'username' => env('INFOBIP_USERNAME'),
    'password' => env('INFOBIP_PASSWORD'),

    /*
     * GRA-0155 - daily SMS-log export + self-service download.
     *
     * export_disk / export_prefix   where the nightly ExportInfobipSmsLogsDaily
     *                               command and the on-demand generator write
     *                               CSVs (the `s3` disk, path reports/...).
     * signed_url_ttl_minutes        TTL of the presigned S3 download URL.
     * max_range_days                cap on the on-demand date-range export so a
     *                               user can't request a full-table dump.
     *
     * Authorisation is the Spatie permission `sms-logs-download` (seed migration
     * + route middleware), so admins can delegate access per-user via Roles &
     * Permissions, not just a fixed role list.
     */
    'export_disk'            => env('INFOBIP_SMS_EXPORT_DISK', 's3'),
    'export_prefix'          => env('INFOBIP_SMS_EXPORT_PREFIX', 'reports/infobip-sms-logs'),
    'signed_url_ttl_minutes' => 15,
    'max_range_days'         => 92,
];
