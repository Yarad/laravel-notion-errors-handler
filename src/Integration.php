<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler;

use Illuminate\Foundation\Configuration\Exceptions;
use Throwable;

class Integration
{
    /**
     * Register the exception handler with Laravel's exception handling system.
     *
     * This is the main entry point for integrating with Laravel 11+ applications.
     *
     * Usage in bootstrap/app.php:
     *
     * ```php
     * use Yarad\NotionExceptionHandler\Integration;
     *
     * return Application::configure(basePath: dirname(__DIR__))
     *     ->withExceptions(function (Exceptions $exceptions) {
     *         Integration::handles($exceptions);
     *     })
     *     ->create();
     * ```
     */
    public static function handles(Exceptions $exceptions): void
    {
        $exceptions->reportable(static function (Throwable $exception) {
            self::captureException($exception);
        });
    }

    /**
     * Capture and report an exception to Notion.
     *
     * This method can be called directly to report exceptions manually:
     *
     * ```php
     * try {
     *     // some code
     * } catch (Exception $e) {
     *     Integration::captureException($e);
     *     throw $e;
     * }
     * ```
     */
    public static function captureException(Throwable $exception): bool
    {
        try {
            $reporter = app(ExceptionReporter::class);

            return $reporter->report($exception);
        } catch (Throwable) {
            // If we can't get the reporter (e.g., during early boot), silently fail
            return false;
        }
    }

    /**
     * Get the exception reporter instance.
     */
    public static function getReporter(): ExceptionReporter
    {
        return app(ExceptionReporter::class);
    }

    /**
     * Check if the integration is properly configured.
     */
    public static function isConfigured(): bool
    {
        try {
            return self::getReporter()->isConfigured();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get the status of the integration.
     *
     * @return array<string, mixed>
     */
    public static function getStatus(): array
    {
        try {
            return self::getReporter()->getStatus();
        } catch (Throwable $e) {
            return [
                'error' => $e->getMessage(),
                'configured' => false,
            ];
        }
    }
}
