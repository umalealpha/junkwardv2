<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Re-KYC Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the Re-KYC service including security,
    | notifications, OCR, and compliance settings.
    |
    */

    'security' => [
        'encryption_key' => env('REKYC_ENCRYPTION_KEY', env('APP_KEY')),
        'encryption_cipher' => 'AES-256-CBC',
        'token_length' => 64,
        'otp_length' => 6,
        'otp_expiry_minutes' => 15,
        'link_expiry_days' => 30,
    ],

    'notifications' => [
        'channels' => [
            'email' => [
                'enabled' => env('REKYC_EMAIL_ENABLED', true),
                'from_address' => env('REKYC_EMAIL_FROM', env('MAIL_FROM_ADDRESS')),
                'from_name' => env('REKYC_EMAIL_FROM_NAME', 'AlphaDirect Re-KYC'),
            ],
            'whatsapp' => [
                'enabled' => env('REKYC_WHATSAPP_ENABLED', true),
                'api_url' => env('REKYC_WHATSAPP_API_URL'),
                'api_token' => env('REKYC_WHATSAPP_API_TOKEN'),
                'phone_number' => env('REKYC_WHATSAPP_PHONE'),
            ],
            'sms' => [
                'enabled' => env('REKYC_SMS_ENABLED', true),
                'provider' => env('REKYC_SMS_PROVIDER', 'infobip'),
                'api_url' => env('REKYC_SMS_API_URL'),
                'api_token' => env('REKYC_SMS_API_TOKEN'),
            ],
        ],
        'templates' => [
            'link_sent' => 'emails.rekyc-link',
            'completion' => 'emails.rekyc-completion',
            'escalation' => 'emails.rekyc-escalation',
        ],
    ],

    'ocr' => [
        'enabled' => env('REKYC_OCR_ENABLED', false),
        'tesseract_enabled' => env('REKYC_TESSERACT_ENABLED', false),
        'tesseract_path' => env('REKYC_TESSERACT_PATH', '/usr/bin/tesseract'),
        'confidence_threshold' => 0.7,
        'supported_languages' => ['eng'],
        'document_types' => [
            'omang' => [
                'fields' => ['omang_number', 'surname', 'first_name', 'date_of_birth', 'place_of_birth', 'gender'],
                'patterns' => [
                    'omang_number' => '/\b\d{9}\b/',
                    'surname' => '/SURNAME[:\s]+([A-Z\s]+)/i',
                    'first_name' => '/FIRST[:\s]+([A-Z\s]+)/i',
                    'date_of_birth' => '/DOB[:\s]+(\d{2}[\/\-]\d{2}[\/\-]\d{4})/',
                    'place_of_birth' => '/POB[:\s]+([A-Z\s,]+)/i',
                    'gender' => '/SEX[:\s]+([MF])/i',
                ],
            ],
            'passport' => [
                'fields' => ['passport_number', 'surname', 'given_names', 'nationality', 'date_of_birth'],
                'patterns' => [
                    'passport_number' => '/PASSPORT[:\s]+([A-Z0-9]{6,12})/i',
                    'surname' => '/SURNAME[:\s]+([A-Z\s]+)/i',
                    'given_names' => '/GIVEN[:\s]+([A-Z\s]+)/i',
                    'nationality' => '/NATIONALITY[:\s]+([A-Z\s]+)/i',
                    'date_of_birth' => '/DOB[:\s]+(\d{2}[\/\-]\d{2}[\/\-]\d{4})/',
                ],
            ],
            'drivers_license' => [
                'fields' => ['license_number', 'full_name', 'date_of_birth', 'license_class'],
                'patterns' => [
                    'license_number' => '/LICENSE[:\s]+([A-Z0-9]{6,15})/i',
                    'full_name' => '/NAME[:\s]+([A-Z\s]+)/i',
                    'date_of_birth' => '/DOB[:\s]+(\d{2}[\/\-]\d{2}[\/\-]\d{4})/',
                    'license_class' => '/CLASS[:\s]+([A-Z0-9]+)/i',
                ],
            ],
        ],
    ],

    'documents' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf'],
        'storage_disk' => 'private',
        'thumbnail_size' => 300,
        'encryption_enabled' => true,
        'hash_algorithm' => 'sha256',
    ],

    'compliance' => [
        'dpa_2018' => [
            'enabled' => true,
            'data_retention_days' => 2555, // 7 years
            'audit_retention_days' => 365, // 1 year
            'consent_required' => true,
            'right_to_erasure' => true,
            'data_portability' => true,
        ],
        'audit' => [
            'enabled' => true,
            'channels' => ['rekyc_audit', 'rekyc_system', 'rekyc_security'],
            'log_level' => 'info',
            'retention_days' => 365,
        ],
        'security' => [
            'rate_limiting' => [
                'otp_attempts' => 5,
                'otp_window_minutes' => 15,
                'link_access_attempts' => 10,
                'link_access_window_minutes' => 60,
            ],
            'ip_whitelist' => env('REKYC_IP_WHITELIST', ''),
            'device_fingerprinting' => true,
            'session_timeout_minutes' => 30,
        ],
    ],

    'campaigns' => [
        'default_settings' => [
            'otp_required' => true,
            'document_upload_required' => false,
            'ocr_enabled' => false,
            'escalation_days' => 7,
            'reminder_days' => [3, 7, 14],
            'max_attempts' => 3,
        ],
        'notification_schedule' => [
            'immediate' => true,
            'reminders' => [3, 7, 14], // days after initial send
            'escalation' => 21, // days after initial send
        ],
    ],

    'api' => [
        'rate_limiting' => [
            'requests_per_minute' => 60,
            'burst_limit' => 100,
        ],
        'cors' => [
            'allowed_origins' => env('REKYC_CORS_ORIGINS', '*'),
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        ],
        'versioning' => [
            'current_version' => 'v1',
            'supported_versions' => ['v1'],
        ],
    ],

    'monitoring' => [
        'health_checks' => [
            'database' => true,
            'storage' => true,
            'notifications' => true,
            'ocr' => false, // Only if OCR is enabled
        ],
        'metrics' => [
            'enabled' => true,
            'collection_interval' => 300, // 5 minutes
            'retention_days' => 30,
        ],
        'alerts' => [
            'enabled' => true,
            'channels' => ['email', 'slack'],
            'thresholds' => [
                'error_rate' => 0.05, // 5%
                'response_time' => 5000, // 5 seconds
                'queue_size' => 1000,
            ],
        ],
    ],
];
