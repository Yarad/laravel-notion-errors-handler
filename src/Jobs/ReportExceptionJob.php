<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yarad\NotionExceptionHandler\Notion\DatabaseManager;
use Yarad\NotionExceptionHandler\RateLimiter\ExceptionRateLimiter;
use Yarad\NotionExceptionHandler\Serialization\ExceptionSerializer;

class ReportExceptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param string $databaseId
     * @param array<string, mixed> $exceptionData Exception data (class, message, code, file, line, trace, previous)
     * @param string $fingerprint
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $databaseId,
        public readonly array $exceptionData,
        public readonly string $fingerprint,
        public readonly array $context,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(
        DatabaseManager $databaseManager,
        ExceptionRateLimiter $rateLimiter,
        ExceptionSerializer $exceptionSerializer,
    ): void {
        // Check rate limiting
        if (!$rateLimiter->shouldAllowGlobal()) {
            $this->logRateLimited('global');

            return;
        }

        if (!$rateLimiter->shouldAllow($this->fingerprint)) {
            $this->logRateLimited($this->fingerprint);

            return;
        }

        // Reconstruct exception from data
        $exception = $exceptionSerializer->fromArray($this->exceptionData);

        // Record to Notion
        $databaseManager->recordException(
            databaseId: $this->databaseId,
            exception: $exception,
            fingerprint: $this->fingerprint,
            context: $this->context,
        );
    }

    /**
     * Log when an exception is rate limited.
     */
    protected function logRateLimited(string $limitType): void
    {
        $exceptionClass = $this->exceptionData['class'] ?? \Exception::class;
        $message = $this->exceptionData['message'] ?? '';

        Log::debug('Notion exception handler: Rate limited (queued job)', [
            'exception' => $exceptionClass,
            'message' => $message,
            'limit_type' => $limitType,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $exceptionClass = $this->exceptionData['class'] ?? \Exception::class;
        $message = $this->exceptionData['message'] ?? '';

        Log::error('Notion exception handler: Failed to report exception (queued job)', [
            'reporting_error' => $exception?->getMessage(),
            'original_exception' => $exceptionClass,
            'original_message' => $message,
        ]);
    }
}
