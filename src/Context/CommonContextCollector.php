<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Context;

class CommonContextCollector implements ContextCollectorInterface
{
    public function __construct(
        private readonly RequestContextCollector $requestCollector,
        private readonly ConsoleContextCollector $consoleCollector,
        private readonly string $environment = 'production',
    ) {
    }

    /**
     * Collect context data based on the current environment.
     *
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $context = $this->isHttpRequest()
            ? $this->requestCollector->collect()
            : $this->consoleCollector->collect();

        $context['environment'] = $this->collectEnvironment();

        return $context;
    }

    /**
     * Get the application environment.
     */
    protected function collectEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Check if we're in an HTTP request context.
     */
    protected function isHttpRequest(): bool
    {
        // Check if we're running in a console environment
        // Using php_sapi_name() is more reliable and works outside Laravel context
        if (php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg') {
            return false;
        }

        // Additionally check if Laravel's app is available and running in console
        try {
            $app = app();
            if (method_exists($app, 'runningInConsole') && $app->runningInConsole()) {
                return false;
            }
        } catch (\Throwable) {
            // If we can't get the app, assume console context
            return false;
        }

        return true;
    }
}
