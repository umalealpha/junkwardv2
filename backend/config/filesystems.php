<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        // Temporary scratch disk for Excel exports + similar one-off file
        // generation. Lives at storage/app/temp on the running container,
        // cleaned up by the report jobs after upload to S3. Required by
        // CustomerBankingReportCron::handle() (Excel::store(..., 'temp', ...))
        // and any future cron that takes the same approach.
        'temp' => [
            'driver' => 'local',
            'root' => storage_path('app/temp'),
        ],

        // When AWS_BUCKET is empty (not configured yet) we silently route the
        // "s3" disk to the local public filesystem so every existing call like
        // ->store(..., 's3') or Storage::disk('s3')->put(...) keeps working
        // without code changes. Once real S3 creds are configured the driver
        // automatically switches back to S3.
        's3' => env('AWS_BUCKET')
            ? [
                'driver'   => 's3',
                'key'      => env('AWS_ACCESS_KEY_ID'),
                'secret'   => env('AWS_SECRET_ACCESS_KEY'),
                'region'   => env('AWS_DEFAULT_REGION'),
                'bucket'   => env('AWS_BUCKET'),
                'url'      => env('AWS_URL'),
                'endpoint' => env('AWS_ENDPOINT'),
            ]
            : [
                'driver'     => 'local',
                'root'       => storage_path('app/public'),
                'url'        => env('APP_URL') . '/storage',
                'visibility' => 'public',
            ],

        // Dedicated bucket for policy documents and static PDF templates
        // (CoverageWiseMultimark wordings, cover pages, declaration pages,
        // etc.). Kept separate from the primary s3 disk so we can:
        //   - host it in the region closest to the V2 cluster (af-south-1)
        //   - version it independently
        //   - scope IAM permissions per bucket
        //
        // Falls back to local disk when AWS_DOCUMENTS_BUCKET is empty, so
        // nothing crashes in dev — the sync command just becomes a no-op.
        'documents' => env('AWS_DOCUMENTS_BUCKET')
            ? [
                'driver' => 's3',
                'key'    => env('AWS_DOCUMENTS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
                'secret' => env('AWS_DOCUMENTS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
                'region' => env('AWS_DOCUMENTS_REGION', 'af-south-1'),
                'bucket' => env('AWS_DOCUMENTS_BUCKET'),
                'url'    => env('AWS_DOCUMENTS_URL'),
            ]
            : [
                'driver'     => 'local',
                'root'       => storage_path('app'),
                'visibility' => 'public',
            ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
