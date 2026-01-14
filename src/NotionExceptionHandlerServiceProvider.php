<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler;

use Illuminate\Support\ServiceProvider;
use Yarad\NotionExceptionHandler\Context\CommonContextCollector;
use Yarad\NotionExceptionHandler\Context\ConsoleContextCollector;
use Yarad\NotionExceptionHandler\Context\ContextCollectorInterface;
use Yarad\NotionExceptionHandler\Context\RequestContextCollector;
use Yarad\NotionExceptionHandler\Fingerprint\FingerprintGenerator;
use Yarad\NotionExceptionHandler\Notion\Content\PageBuilder;
use Yarad\NotionExceptionHandler\Notion\DatabaseManager;
use Yarad\NotionExceptionHandler\Notion\NotionClient;
use Yarad\NotionExceptionHandler\RateLimiter\ExceptionRateLimiter;
use Yarad\NotionExceptionHandler\Serialization\ExceptionSerializer;

class NotionExceptionHandlerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/notion-exceptions.php',
            'notion-exceptions',
        );

        $this->registerNotionClient();
        $this->registerServices();
        $this->registerExceptionReporter();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/notion-exceptions.php' => config_path('notion-exceptions.php'),
            ], 'notion-exceptions-config');
        }
    }

    /**
     * Register the Notion client.
     */
    protected function registerNotionClient(): void
    {
        $this->app->singleton(NotionClient::class, function ($app) {
            /** @var string|null $apiKey */
            $apiKey = config('notion-exceptions.api_key');

            if ($apiKey === null || $apiKey === '') {
                throw new \InvalidArgumentException(
                    'Notion API key is not configured. Please set NOTION_API_KEY in your .env file.',
                );
            }

            return new NotionClient($apiKey);
        });
    }

    /**
     * Register supporting services.
     */
    protected function registerServices(): void
    {
        $this->app->singleton(FingerprintGenerator::class, function () {
            return new FingerprintGenerator();
        });

        $this->app->singleton(ExceptionRateLimiter::class, function ($app) {
            /** @var bool $enabled */
            $enabled = config('notion-exceptions.rate_limit.enabled', true);
            /** @var int $maxPerMinute */
            $maxPerMinute = config('notion-exceptions.rate_limit.max_per_minute', 10);
            /** @var string|null $cacheDriver */
            $cacheDriver = config('notion-exceptions.rate_limit.cache_driver');

            return new ExceptionRateLimiter(
                cache: $app['cache']->driver($cacheDriver),
                enabled: $enabled,
                maxPerMinute: $maxPerMinute,
            );
        });

        $this->app->singleton(RequestContextCollector::class, function () {
            /** @var array<string, bool> $contextConfig */
            $contextConfig = config('notion-exceptions.context', []);

            return new RequestContextCollector($contextConfig);
        });

        $this->app->singleton(ConsoleContextCollector::class, function () {
            return new ConsoleContextCollector();
        });

        $this->app->singleton(CommonContextCollector::class, function ($app) {
            /** @var string $environment */
            $environment = config('app.env', 'production');

            return new CommonContextCollector(
                requestCollector: $app->make(RequestContextCollector::class),
                consoleCollector: $app->make(ConsoleContextCollector::class),
                environment: $environment,
            );
        });

        $this->app->bind(ContextCollectorInterface::class, CommonContextCollector::class);

        $this->app->singleton(PageBuilder::class, function () {
            /** @var array<string, string> $fields */
            $fields = config('notion-exceptions.fields', []);

            return new PageBuilder($fields);
        });

        $this->app->singleton(DatabaseManager::class, function ($app) {
            return new DatabaseManager(
                client: $app->make(NotionClient::class),
                pageBuilder: $app->make(PageBuilder::class),
            );
        });

        $this->app->singleton(ExceptionSerializer::class, function () {
            return new ExceptionSerializer();
        });
    }

    /**
     * Register the exception reporter.
     */
    protected function registerExceptionReporter(): void
    {
        $this->app->singleton(ExceptionReporter::class, function ($app) {
            /** @var bool $enabled */
            $enabled = config('notion-exceptions.enabled', true);
            /** @var string|null $databaseId */
            $databaseId = config('notion-exceptions.database_id');
            /** @var array<class-string<\Throwable>> $ignoredExceptions */
            $ignoredExceptions = config('notion-exceptions.ignored_exceptions', []);

            /** @var string $queueName */
            $queueName = config('notion-exceptions.queue.queue', 'default');

            return new ExceptionReporter(
                fingerprintGenerator: $app->make(FingerprintGenerator::class),
                contextCollector: $app->make(ContextCollectorInterface::class),
                exceptionSerializer: $app->make(ExceptionSerializer::class),
                enabled: $enabled,
                databaseId: $databaseId,
                ignoredExceptions: $ignoredExceptions,
                queueName: $queueName,
            );
        });
    }
}
