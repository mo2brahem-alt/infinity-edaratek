<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'restcountries' => [
        'base_url' => env('RESTCOUNTRIES_BASE_URL', 'https://restcountries.com/v3.1'),
        'timeout' => (int) env('RESTCOUNTRIES_TIMEOUT', 15),
        'connect_timeout' => (int) env('RESTCOUNTRIES_CONNECT_TIMEOUT', 5),
    ],

    'countriesnow' => [
        'base_url' => env('COUNTRIESNOW_BASE_URL', 'https://countriesnow.space/api/v0.1'),
        'timeout' => (int) env('COUNTRIESNOW_TIMEOUT', 20),
        'connect_timeout' => (int) env('COUNTRIESNOW_CONNECT_TIMEOUT', 5),
    ],

    'nager_date' => [
        'base_url' => env('NAGER_DATE_BASE_URL', 'https://date.nager.at/api/v3'),
        'timeout' => (int) env('NAGER_DATE_TIMEOUT', 20),
        'connect_timeout' => (int) env('NAGER_DATE_CONNECT_TIMEOUT', 5),
    ],

    'n8n' => [
        'whatsapp' => [
            'enabled' => (bool) env('N8N_WHATSAPP_ENABLED', false),
            'webhook_url' => env('N8N_WHATSAPP_WEBHOOK_URL'),
            'webhook_secret' => env('N8N_WHATSAPP_WEBHOOK_SECRET'),
            'status_secret' => env('N8N_WHATSAPP_STATUS_SECRET'),
            'claim_secret' => env('N8N_WHATSAPP_CLAIM_SECRET', env('N8N_WHATSAPP_STATUS_SECRET')),
            'timeout' => (int) env('N8N_WHATSAPP_TIMEOUT', 15),
        ],
    ],

    'whatsapp' => [
        'templates' => [
            'notification' => env('WHATSAPP_NOTIFICATION_TEMPLATE', 'edaratek_notifications_enabled'),
            'phone_verification_otp' => env('WHATSAPP_PHONE_VERIFICATION_TEMPLATE', 'edaratek_phone_verification_otp'),
        ],
    ],

];
