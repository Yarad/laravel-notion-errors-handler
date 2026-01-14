<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Context;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

class RequestContextCollector implements ContextCollectorInterface
{
    /**
     * @param array<string, bool> $config Configuration for which context categories to collect
     */
    public function __construct(
        private readonly array $config = [],
    ) {
    }

    /**
     * Collect HTTP request context data.
     *
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $request = $this->getRequest();

        if ($request === null) {
            return [];
        }

        $context = [];

        // Collect request data: URI, method, IP
        if ($this->shouldCollect('request')) {
            $context['request'] = $this->collectRequestData($request);
        }

        // Collect safe headers: User-Agent, Referer, Accept, Content-Type
        if ($this->shouldCollect('headers')) {
            $context['headers'] = $this->collectHeadersData($request);
        }

        // Collect authenticated user ID
        if ($this->shouldCollect('user')) {
            $context['user'] = $this->collectUserData();
        }

        return $context;
    }

    /**
     * Collect request data (URI, method, IP).
     *
     * @return array<string, string|null>
     */
    protected function collectRequestData(Request $request): array
    {
        return array_filter([
            'uri' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ], fn($value) => $value !== null);
    }

    /**
     * Collect safe headers (no sensitive data like Authorization, Cookie).
     *
     * @return array<string, string>
     */
    protected function collectHeadersData(Request $request): array
    {
        $headers = [
            'user_agent' => $request->userAgent(),
            'referer' => $this->getHeaderAsString($request, 'referer'),
            'accept' => $this->getHeaderAsString($request, 'accept'),
            'content_type' => $this->getHeaderAsString($request, 'content-type'),
        ];

        return array_filter($headers, fn($value) => $value !== null && $value !== '');
    }

    /**
     * Get a header value as string.
     */
    protected function getHeaderAsString(Request $request, string $key): ?string
    {
        $value = $request->header($key);

        if (is_array($value)) {
            return $value[0] ?? null;
        }

        return $value;
    }

    /**
     * Collect authenticated user data (ID only).
     *
     * @return array<string, int|string|null>
     */
    protected function collectUserData(): array
    {
        try {
            $userId = Auth::id();
            if ($userId !== null) {
                return ['id' => $userId];
            }
        } catch (\Throwable) {
            // Auth may not be available
        }

        return [];
    }

    /**
     * Check if a specific context category should be collected.
     */
    protected function shouldCollect(string $key): bool
    {
        return $this->config[$key] ?? false;
    }

    /**
     * Get the current request instance.
     */
    protected function getRequest(): ?Request
    {
        try {
            return RequestFacade::instance();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get the configuration.
     *
     * @return array<string, bool>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
