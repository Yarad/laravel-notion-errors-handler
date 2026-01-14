<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Context;

interface ContextCollectorInterface
{
    /**
     * Collect context data.
     *
     * @return array<string, mixed>
     */
    public function collect(): array;
}
