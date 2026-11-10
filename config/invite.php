<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Super Admin Configuration
    |--------------------------------------------------------------------------
    |
    | The secret path used for accessing the Super Admin Filament panel
    | and optional comma-separated list of allowed IP addresses.
    |
    */
    'superadmin_path' => env('SUPERADMIN_PATH', 'sa-x7k2p9q4m1'),
    'superadmin_allowed_ips' => env('SUPERADMIN_ALLOWED_IPS', ''),

    /*
    |--------------------------------------------------------------------------
    | Short Link Domain
    |--------------------------------------------------------------------------
    |
    | Domain or base URL used to generate short links for SMS and card delivery.
    |
    */
    'short_link_domain' => env('SHORT_LINK_DOMAIN', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | SMS Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Default SMS gateway provider and settings for Ethiopia (+251).
    |
    */
    'sms' => [
        'default_provider' => env('SMS_GATEWAY', env('SMS_DEFAULT_PROVIDER', 'log')),
        'sender_id' => env('SMS_SENDER_ID', 'INVITE'),
        'default_country_code' => '+251',
        'afromessage' => [
            'token' => env('AFROMESSAGE_TOKEN', ''),
            'sender_id' => env('AFROMESSAGE_SENDER_ID', env('SMS_SENDER_ID', 'INVITE')),
            'callback_url' => env('AFROMESSAGE_CALLBACK_URL', ''),
        ],
        'geezsms' => [
            'token' => env('GEEZSMS_TOKEN', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest Mode Limits
    |--------------------------------------------------------------------------
    |
    | Default limits and rate controls for guests and public RSVP pages.
    |
    */
    'guest_mode' => [
        'default_max_party_size' => 1,
        'token_length' => 32,
        'rate_limit_per_minute' => 60,
        'max_failed_checkins_before_lock' => 5,
        'scanner_lockout_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content & URL Safety Limits
    |--------------------------------------------------------------------------
    */
    'safety' => [
        'new_account_days' => env('NEW_ACCOUNT_DAYS', 3),
        'new_account_daily_cap' => env('NEW_ACCOUNT_DAILY_CAP', 50),
        'new_account_review_days' => env('NEW_ACCOUNT_REVIEW_DAYS', 3),
        'blocked_domains' => [
            'malware.com',
            'phishing.net',
            'scam-portal.xyz',
            'free-crypto-giveaway.com',
        ],
        'flagged_domains' => [
            'suspicious-external.com',
            'unverified-portal.org',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Locales & Default Currency
    |--------------------------------------------------------------------------
    */
    'supported_locales' => ['en', 'am'],
    'default_currency' => 'ETB',

    /*
    |--------------------------------------------------------------------------
    | Payment Configuration (Step 14A)
    |--------------------------------------------------------------------------
    */
    'payments' => [
        'provider' => env('PAYMENT_PROVIDER', 'manual'),
        'admin_notification_emails' => env('PAYMENT_ADMIN_EMAILS', 'admin@invite.et'),
        'receipt_retention_days' => (int) env('PAYMENT_RECEIPT_RETENTION_DAYS', 90),
        'abandoned_after_days' => (int) env('PAYMENT_ABANDONED_AFTER_DAYS', 7),
        'max_open_payments_per_user' => (int) env('PAYMENT_MAX_OPEN_PER_USER', 3),
        'max_submissions_per_hour' => (int) env('PAYMENT_MAX_SUBMISSIONS_PER_HOUR', 5),
        'notify_user_email' => (bool) env('PAYMENT_NOTIFY_USER_EMAIL', true),
        'notify_user_sms' => (bool) env('PAYMENT_NOTIFY_USER_SMS', false),
    ],
];
