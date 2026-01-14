<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Yarad\NotionExceptionHandler\NotionExceptionHandlerServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NotionExceptionHandlerServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('notion-exceptions.api_key', 'test_api_key');
        $app['config']->set('notion-exceptions.database_id', 'test_database_id');
        $app['config']->set('notion-exceptions.enabled', true);
        $app['config']->set('cache.default', 'array');
    }
}
