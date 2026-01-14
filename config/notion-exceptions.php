<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Notion API Key
    |--------------------------------------------------------------------------
    |
    | Your Notion integration API key. You can create one at:
    | https://www.notion.so/my-integrations
    |
    */
    'api_key' => env('NOTION_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Notion Database ID
    |--------------------------------------------------------------------------
    |
    | The ID of the Notion database where exceptions will be stored.
    | You must create this database manually and share it with your integration.
    |
    */
    'database_id' => env('NOTION_DATABASE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the exception handler. Useful for disabling in
    | local development environments.
    |
    */
    'enabled' => env('NOTION_EXCEPTION_HANDLER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Field Names
    |--------------------------------------------------------------------------
    |
    | Configure the names of the fields in your Notion database.
    | These must match exactly with the property names in your Notion database.
    |
    */
    'fields' => [
        'title' => 'Title',
        'first_seen' => 'First Seen',
        'last_seen' => 'Last Seen',
        'occurrences' => 'Occurrences',
        'environment' => 'Environment',
        'fingerprint' => 'Fingerprint',
        'exception_class' => 'Exception Class',
        'file' => 'File',
        'line' => 'Line',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to prevent overwhelming the Notion API
    | during exception storms.
    |
    */
    'rate_limit' => [
        'enabled' => env('NOTION_RATE_LIMIT_ENABLED', true),
        'max_per_minute' => env('NOTION_RATE_LIMIT_MAX', 10),
        'cache_driver' => env('NOTION_RATE_LIMIT_CACHE_DRIVER', null), // null = default cache driver
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Collection
    |--------------------------------------------------------------------------
    |
    | Configure which context data to collect and store with exceptions.
    |
    | - request: Collects URI, HTTP method, and client IP address
    | - headers: Collects User-Agent, Referer, Accept, Content-Type (no sensitive data)
    | - user: Collects authenticated user ID (if available)
    |
    */
    'context' => [
        'request' => env('NOTION_CONTEXT_REQUEST', true),
        'headers' => env('NOTION_CONTEXT_HEADERS', true),
        'user' => env('NOTION_CONTEXT_USER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | List of exception classes that should not be reported to Notion.
    |
    */
    'ignored_exceptions' => [
        // Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        // Illuminate\Validation\ValidationException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The environment name to be recorded with exceptions. If null,
    | the APP_ENV value will be used.
    |
    */
    'environment' => env('NOTION_EXCEPTION_ENVIRONMENT', null),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure whether to send exceptions asynchronously via queue.
    |
    */
    'queue' => [
        'queue' => env('NOTION_QUEUE_NAME', 'default'),
    ],
];
