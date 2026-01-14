<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler;

use Illuminate\Support\Facades\Log;
use Throwable;
use Yarad\NotionExceptionHandler\Context\ContextCollectorInterface;
use Yarad\NotionExceptionHandler\Fingerprint\FingerprintGenerator;
use Yarad\NotionExceptionHandler\Notion\DatabaseManager;
use Yarad\NotionExceptionHandler\RateLimiter\ExceptionRateLimiter;

class ExceptionReporter
{
    /**
     * @param DatabaseManager $databaseManager
     * @param FingerprintGenerator $fingerprintGenerator
     * @param ExceptionRateLimiter $rateLimiter
     * @param ContextCollectorInterface $contextCollector
     * @param string|null $databaseId
     * @param bool $enabled
     * @param string $environment
     * @param array<class-string<Throwable>> $ignoredExceptions
     */
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly FingerprintGenerator $fingerprintGenerator,
        private readonly ExceptionRateLimiter $rateLimiter,
        private readonly ContextCollectorInterface $contextCollector,
        private readonly ?string $databaseId,
        private readonly bool $enabled,
        private readonly string $environment,
        private readonly array $ignoredExceptions = [],
    ) {
    }

    /**
     * Report an exception to Notion.
     *
     * @param Throwable $exception The exception to report
     *
     * @return bool True if the exception was reported, false otherwise
     */
    public function report(Throwable $exception): bool
    {
        if (!$this->shouldReport($exception)) {
            return false;
        }

        try {
            $fingerprint = $this->fingerprintGenerator->generate($exception);

            // Check rate limiting
            if (!$this->rateLimiter->shouldAllowGlobal()) {
                $this->logRateLimited($exception, 'global');

                return false;
            }

            if (!$this->rateLimiter->shouldAllow($fingerprint)) {
                $this->logRateLimited($exception, $fingerprint);

                return false;
            }

            // Collect context
            $context = $this->contextCollector->collect();

            // Record to Notion
            /** @var string $databaseId */
            $databaseId = $this->databaseId;
            $this->databaseManager->recordException(
                databaseId: $databaseId,
                exception: $exception,
                fingerprint: $fingerprint,
                environment: $this->environment,
                context: $context,
            );

            return true;
        } catch (Throwable $e) {
            // Don't let reporting errors break the application
            $this->logReportingError($e, $exception);

            return false;
        }
    }

    /**
     * Check if an exception should be reported.
     */
    protected function shouldReport(Throwable $exception): bool
    {
        // Check if handler is enabled
        if (!$this->enabled) {
            return false;
        }

        // Check if database ID is configured
        if ($this->databaseId === null || $this->databaseId === '') {
            $this->logConfigurationError('Database ID is not configured');

            return false;
        }

        // Check if exception is in ignored list
        if ($this->isIgnored($exception)) {
            return false;
        }

        return true;
    }

    /**
     * Check if an exception class is in the ignored list.
     */
    protected function isIgnored(Throwable $exception): bool
    {
        foreach ($this->ignoredExceptions as $ignoredClass) {
            if ($exception instanceof $ignoredClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log when an exception is rate limited.
     */
    protected function logRateLimited(Throwable $exception, string $limitType): void
    {
        Log::debug('Notion exception handler: Rate limited', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'limit_type' => $limitType,
        ]);
    }

    /**
     * Log when reporting fails.
     */
    protected function logReportingError(Throwable $reportingError, Throwable $originalException): void
    {
        Log::error('Notion exception handler: Failed to report exception', [
            'reporting_error' => $reportingError->getMessage(),
            'original_exception' => get_class($originalException),
            'original_message' => $originalException->getMessage(),
        ]);
    }

    /**
     * Log configuration errors.
     */
    protected function logConfigurationError(string $message): void
    {
        Log::warning("Notion exception handler: {$message}");
    }

    /**
     * Get the current configuration status.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->enabled,
            'database_id' => $this->databaseId !== null && $this->databaseId !== '' ? '***configured***' : null,
            'environment' => $this->environment,
            'rate_limiting' => [
                'enabled' => $this->rateLimiter->isEnabled(),
                'max_per_minute' => $this->rateLimiter->getMaxPerMinute(),
            ],
            'ignored_exceptions_count' => count($this->ignoredExceptions),
        ];
    }

    /**
     * Check if the reporter is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->enabled
            && $this->databaseId !== null
            && $this->databaseId !== '';
    }

    /**
     * Get the database manager instance.
     */
    public function getDatabaseManager(): DatabaseManager
    {
        return $this->databaseManager;
    }

    /**
     * Get the fingerprint generator instance.
     */
    public function getFingerprintGenerator(): FingerprintGenerator
    {
        return $this->fingerprintGenerator;
    }

    /**
     * Get the rate limiter instance.
     */
    public function getRateLimiter(): ExceptionRateLimiter
    {
        return $this->rateLimiter;
    }

    /**
     * Get the context collector instance.
     */
    public function getContextCollector(): ContextCollectorInterface
    {
        return $this->contextCollector;
    }
}
