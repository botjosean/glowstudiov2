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

    'kapso' => [
        'api_key' => env('KAPSO_API_KEY'),
        'webhook_secret' => env('KAPSO_WEBHOOK_SECRET'),
        // Fails closed: the default answers only the allowlist, so a
        // missing or misspelled value silences the assistant instead of
        // letting it text a real salon's whole client list.
        'reply_mode' => env('KAPSO_REPLY_MODE', 'allowlist'),
        'test_recipients' => env('KAPSO_TEST_RECIPIENTS'),
        'base_url' => env('KAPSO_BASE_URL', 'https://api.kapso.ai'),
        // Tracks Meta's Graph version, which Kapso proxies verbatim.
        'graph_version' => env('KAPSO_GRAPH_VERSION', 'v24.0'),
    ],

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

];
