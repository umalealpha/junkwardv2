<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'daily'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            // 'shared' writes to storage/app/cron-logs/laravel.log on the EFS
            // volume that the cron container also mounts. This is the file
            // the admin Cron Logs page reads — backend's bw_server scheduled
            // commands (customer:banking-report, DomComMonthlyAutoRenew etc.)
            // need to land here so they show up alongside cron_server jobs.
            // storage/logs/laravel.log is per-container ephemeral and not
            // visible to anyone else.
            // 'stderr' pushes every Log::* call to PHP's stderr stream,
            // which the ECS awslogs driver captures to CloudWatch
            // (log group /ecs/graphite-backend). Without it the only
            // persistent record of backend errors is the EFS file —
            // fine for the in-app Cron Logs page but invisible to
            // anyone using AWS Console for deeper diagnostics.
            'channels' => ['single', 'shared', 'stderr'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'shared' => [
            'driver' => 'single',
            'path' => storage_path('app/cron-logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'rekyc_audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/rekyc_audit.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 365, // Keep audit logs for 1 year
        ],

        'rekyc_system' => [
            'driver' => 'daily',
            'path' => storage_path('logs/rekyc_system.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 90, // Keep system logs for 3 months
        ],

        'rekyc_security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/rekyc_security.log'),
            'level' => 'warning', // Only log warnings and above for security
            'days' => 365, // Keep security logs for 1 year
        ],
    ],

];
