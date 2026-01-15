<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Serialization;

use Throwable;
use ReflectionClass;

/**
 * Service for serializing and deserializing exceptions for queue jobs.
 *
 * Exceptions cannot be serialized directly because they contain
 * non-serializable data (closures, resources) in their trace.
 */
class ExceptionSerializer
{
    /**
     * Extract serializable data from an exception.
     *
     * @param Throwable $exception The exception to serialize
     * @return array<string, mixed> Serializable exception data
     */
    public function toArray(Throwable $exception): array
    {
        // Convert trace to serializable format (remove closures and resources)
        $trace = array_map(function ($frame) {
            return [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'],
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
                'args' => $this->serializeTraceArgs($frame['args'] ?? []),
            ];
        }, $exception->getTrace());

        $data = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
        ];

        // Include previous exception if present
        if ($exception->getPrevious() !== null) {
            $data['previous'] = $this->toArray($exception->getPrevious());
        }

        return $data;
    }

    /**
     * Reconstruct exception from serialized data.
     *
     * @param array<string, mixed> $data Serialized exception data
     * @return Throwable Reconstructed exception
     */
    public function fromArray(array $data): Throwable
    {
        $class = $data['class'] ?? \Exception::class;
        $message = $data['message'] ?? '';
        $code = is_numeric($data['code'] ?? 0) ? (int) ($data['code']) : 0;
        $file = $data['file'] ?? '';
        $line = $data['line'] ?? 0;

        // Try to create the original exception class if it exists
        if (class_exists($class) && is_subclass_of($class, Throwable::class)) {
            try {
                /** @var Throwable $exception */
                $exception = new $class($message, $code);
            } catch (\Throwable $e) {
                // Fallback to generic Exception if construction fails
                // Constructors can throw exceptions, so this catch is necessary
                $exception = new \Exception($message, $code);
            }
        } else {
            $exception = new \Exception($message, $code);
        }

        // Set file and line using reflection
        $reflection = new ReflectionClass($exception);
        if ($file !== '') {
            try {
                $fileProperty = $reflection->getProperty('file');
                $fileProperty->setAccessible(true);
                $fileProperty->setValue($exception, $file);
            } catch (\ReflectionException $e) {
                // Ignore if property cannot be set
            }
        }
        if ($line > 0) {
            try {
                $lineProperty = $reflection->getProperty('line');
                $lineProperty->setAccessible(true);
                $lineProperty->setValue($exception, $line);
            } catch (\ReflectionException $e) {
                // Ignore if property cannot be set
            }
        }

        return $exception;
    }

    /**
     * Serialize trace arguments, removing non-serializable values.
     *
     * @param array<int, mixed> $args Trace arguments
     * @return array<int, mixed> Serializable arguments
     */
    protected function serializeTraceArgs(array $args): array
    {
        return array_map(function ($arg) {
            if (is_object($arg)) {
                return get_class($arg);
            }
            if (is_resource($arg)) {
                return '[resource]';
            }
            if (is_array($arg)) {
                return $this->serializeTraceArgs($arg);
            }

            return $arg;
        }, $args);
    }
}
