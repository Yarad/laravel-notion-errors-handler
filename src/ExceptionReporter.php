<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler;

use Illuminate\Support\Facades\Log;
use Throwable;
use Yarad\NotionExceptionHandler\Context\ContextCollectorInterface;
use Yarad\NotionExceptionHandler\Fingerprint\FingerprintGenerator;
use Yarad\NotionExceptionHandler\Jobs\ReportExceptionJob;
use Yarad\NotionExceptionHandler\Serialization\ExceptionSerializer;

class ExceptionReporter
{
    /**
     * @param FingerprintGenerator $fingerprintGenerator
     * @param ContextCollectorInterface $contextCollector
     * @param ExceptionSerializer $exceptionSerializer
     * @param bool $enabled Whether the reporter is enabled
     * @param string|null $databaseId The Notion database ID
     * @param array<class-string<Throwable>> $ignoredExceptions List of exception classes to ignore
     * @param string $queueName The queue name to use
     */
    public function __construct(
        private readonly FingerprintGenerator $fingerprintGenerator,
        private readonly ContextCollectorInterface $contextCollector,
        private readonly ExceptionSerializer $exceptionSerializer,
        private readonly bool $enabled = true,
        private readonly ?string $databaseId = null,
        private readonly array $ignoredExceptions = [],
        private readonly string $queueName = 'default',
    ) {
    }

    /**
     * Report an exception to Notion.
     *
     * @param Throwable $exception The exception to report
     *
     * @return bool True if the exception was reported (or dispatched), false otherwise
     */
    public function report(Throwable $exception): bool
    {
        if (!$this->shouldReport($exception)) {
            return false;
        }

        try {
            $fingerprint = $this->fingerprintGenerator->generate($exception);

            // Collect context synchronously (required for request data)
            $context = $this->contextCollector->collect();

            // databaseId is guaranteed to be non-null and non-empty after shouldReport check
            $databaseId = (string) $this->databaseId;

            $this->dispatchToQueue(
                databaseId: $databaseId,
                exception: $exception,
                fingerprint: $fingerprint,
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
     * Dispatch exception reporting to the queue.
     *
     * @param string $databaseId The Notion database ID
     * @param Throwable $exception The exception to report
     * @param string $fingerprint The exception fingerprint
     * @param array<string, mixed> $context The collected context
     */
    protected function dispatchToQueue(
        string $databaseId,
        Throwable $exception,
        string $fingerprint,
        array $context,
    ): void {
        // Extract exception data for serialization (exceptions can't be serialized directly)
        $exceptionData = $this->exceptionSerializer->toArray($exception);

        $job = new ReportExceptionJob(
            databaseId: $databaseId,
            exceptionData: $exceptionData,
            fingerprint: $fingerprint,
            context: $context,
        );

        $job->onQueue($this->queueName);

        dispatch($job);
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
        $context = $this->contextCollector->collect();

        return [
            'enabled' => $this->enabled,
            'database_id' => $this->databaseId !== null && $this->databaseId !== '' ? '***configured***' : null,
            'environment' => $context['environment'] ?? 'unknown',
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

}
