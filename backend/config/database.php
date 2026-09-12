<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_SQLITE_DATABASE', database_path('sanctum.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        // ============================================================
        // 'mysql' (default) — LEGACY V1 DATA, READ-ONLY
        // Phase 1: V2 reads V1 prod data via the af-south-1 read replica.
        // No writes. Replica enforces super_read_only=ON; this is defence
        // in depth in case any V2 code path tries to INSERT/UPDATE here.
        // ============================================================
        'mysql' => [
            'read' => [
                'host' => array_values(array_filter([
                    env('DB_HOST_READONLY'),
                    env('DB_HOST_RO'),
                ])) ?: [env('DB_HOST', '127.0.0.1')],
                'database' => env('DB_DATABASE_READONLY') ?: env('DB_DATABASE', 'forge'),
                'username' => env('DB_USERNAME_READONLY') ?: env('DB_USERNAME', 'forge'),
                'password' => env('DB_PASSWORD_READONLY') ?: env('DB_PASSWORD', ''),
                'port' => env('DB_PORT', '3306'),
            ],
            'write' => [
                // Pre-cutover this pointed at DB_HOST_READONLY as a safety valve
                // to prevent V2 from writing to V1 prod (V1 replica enforced
                // super_read_only; any accidental write failed harmlessly).
                // Post-cutover, once DB_HOST_READONLY was repointed at the V2
                // read replica, the safety valve started REJECTING REAL V2
                // WRITES that legacy code paths route through the default
                // 'mysql' connection — notification_logs delivery-receipt
                // updates being the live case (Infobip / Mailgun webhooks).
                // To unbreak those without forcing every legacy caller to
                // switch to DB::connection('mysql_system'), writes via this
                // connection now silently land on the V2 master. New code
                // should still prefer mysql_system for clarity.
                'host' => env('DB_HOST_SYSTEM', env('DB_HOST', '127.0.0.1')),
            ],
            'sticky' => true,
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::ATTR_PERSISTENT => true,
            ]) : [],
        ],

        // ============================================================
        // 'mysql_system' — V2 OPERATIONS DB (graphite-v2-prod, af-south-1)
        // V2-owned tables only: anomaly_findings, public_otps,
        // v2_pdf_jobs, api_error_log, policy_lifecycle (V2-side),
        // sessions, cache, jobs, failed_jobs, RBAC.
        // Local writes — no cross-region penalty.
        // Phase 3: this becomes the master DB; 'mysql' is decommissioned.
        //
        // Aligned with existing task-def env convention: DB_*_SYSTEM
        // ============================================================
        'mysql_system' => [
            'driver'    => 'mysql',
            // Read/write split — Laravel auto-routes SELECT to read hosts and
            // INSERT/UPDATE/DELETE + transactions to the write host. sticky=true
            // forces post-write reads back to the write host within the same
            // request to avoid replication-lag inconsistency.
            //
            // ENV wiring REUSES the existing DB_HOST_READONLY / DB_HOST_RO
            // pair that the 'mysql' connection already uses (already present
            // on every task def) — no new env vars introduced. Each task def
            // overrides the VALUES to control routing:
            //   Backend task def : both point at replica(s) for distribution
            //   Cron task def    : one set, one unset → pin cron reads to
            //                      that single replica (keeps cron off the
            //                      replica serving the reporting portal)
            //   Both unset       : graceful fallback to DB_HOST_SYSTEM
            //                      (master) — today's behaviour, no breakage
            //
            // username/password for the read connection are taken from the
            // existing DB_USERNAME_READONLY / DB_PASSWORD_READONLY pair
            // (also already present on every task def). Falls back to the
            // _SYSTEM credentials if the _READONLY pair isn't set.
            'read' => [
                'host' => array_values(array_filter([
                    env('DB_HOST_READONLY'),
                    env('DB_HOST_RO'),
                ])) ?: [env('DB_HOST_SYSTEM', env('DB_HOST', '127.0.0.1'))],
                'username' => env('DB_USERNAME_READONLY') ?: env('DB_USERNAME_SYSTEM', env('DB_USERNAME', 'forge')),
                'password' => env('DB_PASSWORD_READONLY') ?: env('DB_PASSWORD_SYSTEM', env('DB_PASSWORD', '')),
            ],
            'write' => [
                'host' => env('DB_HOST_SYSTEM', env('DB_HOST', '127.0.0.1')),
            ],
            'sticky'    => true,
            'port'      => env('DB_PORT_SYSTEM', env('DB_PORT', '3306')),
            'database'  => env('DB_DATABASE_SYSTEM', env('DB_DATABASE', 'Graphite_live')),
            'username'  => env('DB_USERNAME_SYSTEM', env('DB_USERNAME', 'forge')),
            'password'  => env('DB_PASSWORD_SYSTEM', env('DB_PASSWORD', '')),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'prefix_indexes' => true,
            'strict'    => false,
            'engine'    => null,
            'options'   => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::ATTR_PERSISTENT => true,
            ]) : [],
        ],

        // 'mysql_write' — kept as an ALIAS of 'mysql_system' for backwards
        // compatibility. Pre-pivot, this connection wrote to V1 prod via
        // DB_HOST_WRITE; post-pivot V2 must NOT write to V1, so all writes
        // go to v2-prod (same target as mysql_system). Existing call sites
        // using DB::connection('mysql_write') therefore Just Work.
        // New code should prefer 'mysql_system' for clarity.
        'mysql_write' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST_SYSTEM', env('DB_HOST', '127.0.0.1')),
            'port'      => env('DB_PORT_SYSTEM', env('DB_PORT', '3306')),
            'database'  => env('DB_DATABASE_SYSTEM', env('DB_DATABASE', 'Graphite_live')),
            'username'  => env('DB_USERNAME_SYSTEM', env('DB_USERNAME', 'forge')),
            'password'  => env('DB_PASSWORD_SYSTEM', env('DB_PASSWORD', '')),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            'options'   => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // 'mysql2' — sink for API request tracking (track_a_p_i_requests),
        // which lives in the `graphite_before_update` database. This MUST hit a
        // WRITABLE host: the primary 'mysql' connection points at the
        // super_read_only replica, so inserts there fail. Defaults therefore
        // fall back to the V2-owned writable system host/credentials
        // (DB_*_SYSTEM), not the read-replica primary. Previously these
        // defaulted to forge/forge, so every tracking insert failed silently —
        // which is why API tracking stopped recording after the V2 cutover.
        'mysql2' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_SECOND', env('DB_HOST_SYSTEM', env('DB_HOST', '127.0.0.1'))),
            'port' => env('DB_PORT_SECOND', env('DB_PORT_SYSTEM', env('DB_PORT', '3306'))),
            'database' => env('DB_DATABASE_SECOND', 'graphite_before_update'),
            'username' => env('DB_USERNAME_SECOND', env('DB_USERNAME_SYSTEM', env('DB_USERNAME', 'forge'))),
            'password' => env('DB_PASSWORD_SECOND', env('DB_PASSWORD_SYSTEM', env('DB_PASSWORD', ''))),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        'mysql3' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE_THIRD', 'graphite_archive'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        'mysql4' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_LOGS', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE_LOGS', 'forge'),
            'username' => env('DB_USERNAME_LOGS', 'forge'),
            'password' => env('DB_PASSWORD_LOGS', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
