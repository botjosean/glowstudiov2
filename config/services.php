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

    'assistant' => [
        'api_key' => env('ASSISTANT_API_KEY'),
        'base_url' => env('ASSISTANT_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('ASSISTANT_MODEL', 'openai/gpt-oss-120b'),
        'timeout' => env('ASSISTANT_TIMEOUT', 30),
        'temperature' => env('ASSISTANT_TEMPERATURE', 0.3),
        'max_completion_tokens' => env('ASSISTANT_MAX_COMPLETION_TOKENS', 1024),
        // Only sent when non-empty: it is a gpt-oss extension, and other
        // models reject unknown fields.
        'reasoning_effort' => env('ASSISTANT_REASONING_EFFORT'),
        // Bounds the tool-calling loop: a model that keeps calling tools would
        // otherwise hold a worker and spend tokens with nothing to show.
        'max_iterations' => env('ASSISTANT_MAX_ITERATIONS', 6),
        // The biggest lever on tokens per minute: the history is resent on
        // every round of the tool-calling loop.
        'history_messages' => env('ASSISTANT_HISTORY_MESSAGES', 8),
        // Set to a date to give every conversation a clean slate from that
        // moment on, without deleting anything at Kapso.
        'history_since' => env('ASSISTANT_HISTORY_SINCE'),
        // How long to stay out of a conversation somebody at the salon
        // answered by hand. Long enough for a real back-and-forth,
        // short enough that one stray message does not strand a client.
        'human_handover_minutes' => env('ASSISTANT_HUMAN_HANDOVER_MINUTES', 15),
        // How long the assistant stays quiet after telling a client "a person
        // will answer you" — trying again right away is what produced two
        // wait messages in a row for a real client.
        'handoff_pause_minutes' => env('ASSISTANT_HANDOFF_PAUSE_MINUTES', 120),
        // Receptionist mode only. How cold a conversation has to go before its
        // two messages are offered again — without it a client who comes back
        // months later is met with silence.
        'receptionist_rearm_days' => env('ASSISTANT_RECEPTIONIST_REARM_DAYS', 7),
        // How long a request may sit unanswered before the professional is
        // told. The receptionist hands every conversation to a person, so a
        // person forgetting is the failure this mode introduces.
        'lead_alert_hours' => env('ASSISTANT_LEAD_ALERT_HOURS', 3),
        // Receptionist mode only. How long a client waits in silence after the
        // greeting before the second message arrives on its own, instead of
        // only when she writes again.
        'receptionist_follow_up_minutes' => env('ASSISTANT_RECEPTIONIST_FOLLOW_UP_MINUTES', 10),
    ],

    // Where the panel's help pill sends a professional who needs a person.
    // Unset means the pill says help is coming instead of opening nothing.
    'support' => [
        'whatsapp' => env('SUPPORT_WHATSAPP'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'kapso' => [
        'api_key' => env('KAPSO_API_KEY'),
        'webhook_secret' => env('KAPSO_WEBHOOK_SECRET'),
        // Fails closed: the default answers only the allowlist, so a
        // missing or misspelled value silences the assistant instead of
        // letting it text a real salon's whole client list.
        'reply_mode' => env('KAPSO_REPLY_MODE', 'allowlist'),
        'test_recipients' => env('KAPSO_TEST_RECIPIENTS'),
        // A number still shared with a professional's personal WhatsApp while
        // she migrates it to the business. See ReplyPolicy: any sender saved
        // as a contact on that phone is never answered. Leave unset once the
        // number is business-only.
        'personal_phone_number_id' => env('KAPSO_PERSONAL_PHONE_NUMBER_ID'),
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
