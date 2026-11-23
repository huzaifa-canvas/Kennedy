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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'revenuecat' => [
        // Shared secret set as the Authorization header in the RevenueCat dashboard.
        'webhook_secret' => env('REVENUECAT_WEBHOOK_SECRET'),

        // Secret (sk_...) REST API key. Used to read a subscriber's real
        // entitlement from RevenueCat instead of trusting the app or a
        // possibly out-of-order webhook payload. Never ship this in the app.
        'secret_api_key' => env('REVENUECAT_SECRET_API_KEY'),
        'api_base' => env('REVENUECAT_API_BASE', 'https://api.revenuecat.com/v1'),

        // Entitlement identifier configured in the RevenueCat dashboard.
        'entitlement' => env('REVENUECAT_ENTITLEMENT', 'pro'),

        // Used to build the Google Play "Manage Subscription" link.
        'android_package' => env('ANDROID_PACKAGE_NAME'),
    ],

];
