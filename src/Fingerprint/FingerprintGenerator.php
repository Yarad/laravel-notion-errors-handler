<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Fingerprint;

use Throwable;

class FingerprintGenerator
{
    /**
     * Generate a unique fingerprint for an exception.
     *
     * The fingerprint is based on:
     * - Exception class name
     * - Exception message
     * - File where exception occurred
     * - Line number where exception occurred
     *
     * This follows Sentry's approach to exception grouping.
     */
    public function generate(Throwable $exception): string
    {
        $components = [
            $this->getExceptionClass($exception),
            $this->normalizeMessage($exception->getMessage()),
            $exception->getFile(),
            (string) $exception->getLine(),
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Generate a short fingerprint (first 16 characters) for display.
     */
    public function generateShort(Throwable $exception): string
    {
        return substr($this->generate($exception), 0, 16);
    }

    /**
     * Get the exception class name.
     */
    protected function getExceptionClass(Throwable $exception): string
    {
        return get_class($exception);
    }

    /**
     * Normalize the exception message by removing dynamic values.
     *
     * This helps group similar exceptions that differ only in dynamic values
     * like IDs, timestamps, etc.
     */
    protected function normalizeMessage(string $message): string
    {
        // Remove common dynamic values
        $patterns = [
            // UUIDs
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i' => '{uuid}',
            // Numeric IDs
            '/\b\d{5,}\b/' => '{id}',
            // Timestamps
            '/\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}/' => '{timestamp}',
            // Email addresses
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '{email}',
            // IP addresses
            '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/' => '{ip}',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $message) ?? $message;
    }

    /**
     * Create fingerprint data array for storage/display.
     *
     * @return array{fingerprint: string, short: string, class: string, message: string, file: string, line: int}
     */
    public function createFingerprintData(Throwable $exception): array
    {
        return [
            'fingerprint' => $this->generate($exception),
            'short' => $this->generateShort($exception),
            'class' => $this->getExceptionClass($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }
}
