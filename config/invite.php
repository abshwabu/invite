<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Super Admin Path
    |--------------------------------------------------------------------------
    |
    | The secret path used for accessing the Super Admin Filament panel.
    |
    */
    'superadmin_path' => env('SUPERADMIN_PATH', 'cp-super-admin'),

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
        'default_provider' => env('SMS_DEFAULT_PROVIDER', 'afromessage'),
        'sender_id' => env('SMS_SENDER_ID', 'INVITE'),
        'default_country_code' => '+251',
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
    | Supported Locales & Default Currency
    |--------------------------------------------------------------------------
    */
    'supported_locales' => ['en', 'am'],
    'default_currency' => 'ETB',
];
