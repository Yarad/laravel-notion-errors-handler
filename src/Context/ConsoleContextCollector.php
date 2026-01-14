<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Context;

class ConsoleContextCollector implements ContextCollectorInterface
{
    /**
     * Collect console context data.
     *
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $context = [
            'type' => 'console',
        ];

        // Try to get the current command if available
        if (defined('ARTISAN_BINARY')) {
            $context['command'] = implode(' ', $_SERVER['argv'] ?? []);
        }

        return $context;
    }
}
