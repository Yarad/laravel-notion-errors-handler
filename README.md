# Laravel Notion Exception Handler

A Laravel package that reports exceptions to a Notion database, similar to Sentry.

## Features

- 📝 Automatic exception reporting to Notion database
- 🔄 Groups identical exceptions (updates occurrence count instead of creating duplicates)
- 📊 Collects request context (URL, method, IP, etc.)
- ⚙️ Highly configurable field names and behaviors
- 🎯 Laravel 10, 11, and 12 support

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x
- A Notion integration and database

## Installation

```bash
composer require yarad/laravel-notion-errors-handler
```

## Configuration

### 1. Create a Notion Integration

1. Go to [My Integrations](https://www.notion.so/my-integrations)
2. Click "New integration"
3. Give it a name and select the workspace
4. Copy the "Internal Integration Token"

### 2. Create a Notion Database

Create a database in Notion with the following properties:

| Property Name   | Type   |
|-----------------|--------|
| Title           | Title  |
| First Seen      | Date   |
| Last Seen       | Date   |
| Occurrences     | Number |
| Environment     | Select |
| Exception Class | Text   |
| File            | Text   |
| Line            | Number |
| Fingerprint     | Text   |

**Important:** Share the database with your integration by clicking "Share" and adding your integration.

### 3. Get the Database ID

Copy the database ID from the URL:
```
https://www.notion.so/myworkspace/a8aec43384f447ed84390e8e42c2e089?v=...
                                  ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
                                  This is the database ID
```

### 4. Configure Environment Variables

Add to your `.env` file:

```env
NOTION_API_KEY=secret_xxx
NOTION_DATABASE_ID=a8aec43384f447ed84390e8e42c2e089
```

### 5. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=notion-exceptions-config
```

## Usage

### Laravel 11+

In your `bootstrap/app.php`:

```php
use Yarad\NotionExceptionHandler\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(...)
    ->withMiddleware(...)
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
```

### Manual Exception Reporting

```php
use Yarad\NotionExceptionHandler\Integration;

try {
    // Your code
} catch (Exception $e) {
    Integration::captureException($e);
    throw $e;
}
```

### Using the Facade

```php
use Yarad\NotionExceptionHandler\Facades\NotionExceptionHandler;

NotionExceptionHandler::report($exception);
NotionExceptionHandler::isConfigured();
NotionExceptionHandler::getStatus();
```

## Configuration Options

```php
// config/notion-exceptions.php

return [
    // Notion API credentials
    'api_key' => env('NOTION_API_KEY'),
    'database_id' => env('NOTION_DATABASE_ID'),

    // Enable/disable the handler
    'enabled' => env('NOTION_EXCEPTION_HANDLER_ENABLED', true),

    // Custom field names (must match your Notion database)
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

    // Context categories to collect
    'context' => [
        'request' => true,  // URI, method, IP
        'headers' => true,  // User-Agent, Referer, Accept, Content-Type
        'user' => true,     // Authenticated user ID
    ],

    // Exceptions to ignore
    'ignored_exceptions' => [
        // Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    // Environment name (defaults to APP_ENV)
    'environment' => env('NOTION_EXCEPTION_ENVIRONMENT'),
];
```

## How Exception Grouping Works

Exceptions are grouped by a fingerprint generated from:
- Exception class name
- Exception message (normalized to remove dynamic values like UUIDs, IDs, timestamps)
- File path
- Line number

This means the same error occurring multiple times will update a single Notion entry instead of creating duplicates.

## Page Content

Each exception page in Notion includes:
- **Exception Message** - The full error message
- **Stack Trace** - Code block with the stack trace
- **Request Context** - Grouped by category:
  - **Request**: URI, HTTP method, client IP
  - **Headers**: User-Agent, Referer, Accept, Content-Type (no sensitive headers)
  - **User**: Authenticated user ID (if available)

## Development

### Running Tests

```bash
# All tests
./vendor/bin/phpunit

# Unit tests only
./vendor/bin/phpunit --testsuite Unit

# Feature tests only
./vendor/bin/phpunit --testsuite Feature
```

### Code Quality

```bash
# Static analysis
./vendor/bin/phpstan analyse

# Code style fix
./vendor/bin/php-cs-fixer fix
```

### Testing with Real Notion API

```bash
NOTION_API_KEY=xxx NOTION_DATABASE_ID=xxx ./vendor/bin/phpunit --filter RealNotionApiTest
```

## License

MIT License. See [LICENSE](LICENSE) file.
