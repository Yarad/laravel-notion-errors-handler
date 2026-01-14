<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Facades;

use Illuminate\Support\Facades\Facade;
use Throwable;
use Yarad\NotionExceptionHandler\ExceptionReporter;

/**
 * @method static bool report(Throwable $exception)
 * @method static bool isConfigured()
 * @method static array getStatus()
 *
 * @see ExceptionReporter
 */
class NotionExceptionHandler extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ExceptionReporter::class;
    }
}
