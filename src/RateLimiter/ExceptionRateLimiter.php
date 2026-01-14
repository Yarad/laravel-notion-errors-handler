<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\RateLimiter;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

class ExceptionRateLimiter
{
    private const CACHE_KEY_PREFIX = 'notion_exception_rate_limit:';

    private const CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly bool $enabled = true,
        private readonly int $maxPerMinute = 10,
    ) {
    }

    /**
     * Check if the exception should be rate limited.
     *
     * @param string $fingerprint The exception fingerprint
     *
     * @return bool True if the exception should be allowed, false if rate limited
     */
    public function shouldAllow(string $fingerprint): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $key = $this->getCacheKey($fingerprint);
        $currentCount = $this->getCurrentCount($key);

        if ($currentCount >= $this->maxPerMinute) {
            return false;
        }

        $this->incrementCount($key);

        return true;
    }

    /**
     * Check if the global rate limit should allow a new exception.
     *
     * @return bool True if allowed, false if rate limited
     */
    public function shouldAllowGlobal(): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $key = $this->getCacheKey('global');
        $currentCount = $this->getCurrentCount($key);

        if ($currentCount >= $this->maxPerMinute) {
            return false;
        }

        $this->incrementCount($key);

        return true;
    }

    /**
     * Get the current count for a rate limit key.
     */
    protected function getCurrentCount(string $key): int
    {
        /** @var int|null $count */
        $count = $this->cache->get($key);

        return $count ?? 0;
    }

    /**
     * Increment the count for a rate limit key.
     */
    protected function incrementCount(string $key): void
    {
        if (!$this->cache->has($key)) {
            $this->cache->put($key, 1, self::CACHE_TTL_SECONDS);
        } else {
            $this->cache->increment($key);
        }
    }

    /**
     * Get the cache key for a fingerprint.
     */
    protected function getCacheKey(string $fingerprint): string
    {
        return self::CACHE_KEY_PREFIX . $fingerprint;
    }

    /**
     * Get the remaining attempts for a fingerprint.
     */
    public function getRemainingAttempts(string $fingerprint): int
    {
        if (!$this->enabled) {
            return PHP_INT_MAX;
        }

        $key = $this->getCacheKey($fingerprint);
        $currentCount = $this->getCurrentCount($key);

        return max(0, $this->maxPerMinute - $currentCount);
    }

    /**
     * Clear the rate limit for a specific fingerprint.
     */
    public function clear(string $fingerprint): void
    {
        $key = $this->getCacheKey($fingerprint);
        $this->cache->forget($key);
    }

    /**
     * Clear all rate limits.
     */
    public function clearAll(): void
    {
        // Note: This only clears the global rate limit
        // Individual fingerprint limits will expire naturally
        $this->cache->forget($this->getCacheKey('global'));
    }

    /**
     * Check if rate limiting is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the maximum allowed requests per minute.
     */
    public function getMaxPerMinute(): int
    {
        return $this->maxPerMinute;
    }
}
