<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;
use Yarad\NotionExceptionHandler\RateLimiter\ExceptionRateLimiter;

class ExceptionRateLimiterTest extends TestCase
{
    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new Repository(new ArrayStore());
    }

    public function testShouldAllowWhenRateLimitingIsDisabled(): void
    {
        // Given
        $rateLimiter = $this->givenRateLimiterWithConfig(enabled: false, maxPerMinute: 1);

        // When
        $result1 = $this->whenCheckShouldAllow($rateLimiter, 'fingerprint1');
        $result2 = $this->whenCheckShouldAllow($rateLimiter, 'fingerprint1');
        $result3 = $this->whenCheckShouldAllow($rateLimiter, 'fingerprint1');

        // Then
        $this->thenAllShouldBeAllowed($result1, $result2, $result3);
    }

    public function testShouldAllowFirstRequest(): void
    {
        // Given
        $rateLimiter = $this->givenRateLimiterWithConfig(enabled: true, maxPerMinute: 10);

        // When
        $result = $this->whenCheckShouldAllow($rateLimiter, 'fingerprint1');

        // Then
        $this->thenShouldBeAllowed($result);
    }

    public function testShouldBlockAfterLimitExceeded(): void
    {
        // Given
        $rateLimiter = $this->givenRateLimiterWithConfig(enabled: true, maxPerMinute: 2);
        $fingerprint = 'test_fingerprint';

        // When - make 2 requests (within limit)
        $result1 = $this->whenCheckShouldAllow($rateLimiter, $fingerprint);
        $result2 = $this->whenCheckShouldAllow($rateLimiter, $fingerprint);
        // When - make 3rd request (exceeds limit)
        $result3 = $this->whenCheckShouldAllow($rateLimiter, $fingerprint);

        // Then
        $this->thenShouldBeAllowed($result1);
        $this->thenShouldBeAllowed($result2);
        $this->thenShouldBeBlocked($result3);
    }

    public function testDifferentFingerprintsHaveSeparateLimits(): void
    {
        // Given
        $rateLimiter = $this->givenRateLimiterWithConfig(enabled: true, maxPerMinute: 1);

        // When
        $result1 = $this->whenCheckShouldAllow($rateLimiter, 'fingerprint1');
        $result2 = $this->whenCheckShouldAllow($rateLimiter, 'fingerprint2');
        $result3 = $this->whenCheckShouldAllow($rateLimiter, 'fingerprint1'); // should be blocked

        // Then
        $this->thenShouldBeAllowed($result1);
        $this->thenShouldBeAllowed($result2);
        $this->thenShouldBeBlocked($result3);
    }

    public function testGlobalRateLimitWorks(): void
    {
        // Given
        $rateLimiter = $this->givenRateLimiterWithConfig(enabled: true, maxPerMinute: 2);

        // When
        $result1 = $this->whenCheckShouldAllowGlobal($rateLimiter);
        $result2 = $this->whenCheckShouldAllowGlobal($rateLimiter);
        $result3 = $this->whenCheckShouldAllowGlobal($rateLimiter);

        // Then
        $this->thenShouldBeAllowed($result1);
        $this->thenShouldBeAllowed($result2);
        $this->thenShouldBeBlocked($result3);
    }

    public function testGetRemainingAttemptsReturnsCorrectValue(): void
    {
        // Given
        $rateLimiter = $this->givenRateLimiterWithConfig(enabled: true, maxPerMinute: 5);
        $fingerprint = 'test_fingerprint';

        // When
        $remaining1 = $this->whenGetRemainingAttempts($rateLimiter, $fingerprint);
        $this->whenCheckShouldAllow($rateLimiter, $fingerprint);
        $this->whenCheckShouldAllow($rateLimiter, $fingerprint);
        $remaining2 = $this->whenGetRemainingAttempts($rateLimiter, $fingerprint);

        // Then
        $this->assertEquals(5, $remaining1);
        $this->assertEquals(3, $remaining2);
    }

    public function testClearRemovesRateLimitForFingerprint(): void
    {
        // Given
        $rateLimiter = $this->givenRateLimiterWithConfig(enabled: true, maxPerMinute: 1);
        $fingerprint = 'test_fingerprint';
        $this->whenCheckShouldAllow($rateLimiter, $fingerprint);

        // When
        $resultBeforeClear = $this->whenCheckShouldAllow($rateLimiter, $fingerprint);
        $rateLimiter->clear($fingerprint);
        $resultAfterClear = $this->whenCheckShouldAllow($rateLimiter, $fingerprint);

        // Then
        $this->thenShouldBeBlocked($resultBeforeClear);
        $this->thenShouldBeAllowed($resultAfterClear);
    }

    public function testIsEnabledReturnsCorrectValue(): void
    {
        // Given
        $enabledLimiter = $this->givenRateLimiterWithConfig(enabled: true, maxPerMinute: 10);
        $disabledLimiter = $this->givenRateLimiterWithConfig(enabled: false, maxPerMinute: 10);

        // When & Then
        $this->assertTrue($enabledLimiter->isEnabled());
        $this->assertFalse($disabledLimiter->isEnabled());
    }

    public function testGetMaxPerMinuteReturnsCorrectValue(): void
    {
        // Given
        $rateLimiter = $this->givenRateLimiterWithConfig(enabled: true, maxPerMinute: 42);

        // When & Then
        $this->assertEquals(42, $rateLimiter->getMaxPerMinute());
    }

    // Given methods

    private function givenRateLimiterWithConfig(bool $enabled, int $maxPerMinute): ExceptionRateLimiter
    {
        return new ExceptionRateLimiter(
            cache: $this->cache,
            enabled: $enabled,
            maxPerMinute: $maxPerMinute,
        );
    }

    // When methods

    private function whenCheckShouldAllow(ExceptionRateLimiter $rateLimiter, string $fingerprint): bool
    {
        return $rateLimiter->shouldAllow($fingerprint);
    }

    private function whenCheckShouldAllowGlobal(ExceptionRateLimiter $rateLimiter): bool
    {
        return $rateLimiter->shouldAllowGlobal();
    }

    private function whenGetRemainingAttempts(ExceptionRateLimiter $rateLimiter, string $fingerprint): int
    {
        return $rateLimiter->getRemainingAttempts($fingerprint);
    }

    // Then methods

    private function thenShouldBeAllowed(bool $result): void
    {
        $this->assertTrue($result, 'Expected request to be allowed');
    }

    private function thenShouldBeBlocked(bool $result): void
    {
        $this->assertFalse($result, 'Expected request to be blocked');
    }

    private function thenAllShouldBeAllowed(bool ...$results): void
    {
        foreach ($results as $index => $result) {
            $this->assertTrue($result, "Expected request #{$index} to be allowed");
        }
    }
}
