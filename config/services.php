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

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'timeout' => env('GROQ_TIMEOUT', 30),
        'temperature' => env('GROQ_TEMPERATURE', 0.3),
        'max_completion_tokens' => env('GROQ_MAX_COMPLETION_TOKENS', 1024),
        'reasoning_effort' => env('GROQ_REASONING_EFFORT', 'medium'),
        // Bounds the tool-calling loop: a model that keeps calling tools would
        // otherwise hold a worker and spend tokens with nothing to show.
        'max_iterations' => env('GROQ_MAX_ITERATIONS', 6),
    ],

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
