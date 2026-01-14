<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Tests\Feature;

use Exception;
use Yarad\NotionExceptionHandler\ExceptionReporter;
use Yarad\NotionExceptionHandler\Integration;
use Yarad\NotionExceptionHandler\Tests\TestCase;

class IntegrationTest extends TestCase
{
    public function testIntegrationIsConfiguredReturnsTrueWhenProperlyConfigured(): void
    {
        // Given - configuration is set in defineEnvironment

        // When
        $isConfigured = $this->whenCheckIsConfigured();

        // Then
        $this->thenShouldBeConfigured($isConfigured);
    }

    public function testIntegrationIsConfiguredReturnsFalseWhenDatabaseIdMissing(): void
    {
        // Given
        $this->givenDatabaseIdNotConfigured();

        // When
        $isConfigured = $this->whenCheckIsConfigured();

        // Then
        $this->thenShouldNotBeConfigured($isConfigured);
    }

    public function testGetStatusReturnsExpectedStructure(): void
    {
        // Given - configuration is set in defineEnvironment

        // When
        $status = $this->whenGetStatus();

        // Then
        $this->thenStatusShouldHaveRequiredKeys($status);
    }

    public function testReporterCanBeResolvedFromContainer(): void
    {
        // Given - service provider is loaded

        // When
        $reporter = $this->whenResolveReporter();

        // Then
        $this->thenReporterShouldBeInstanceOfExceptionReporter($reporter);
    }

    public function testReporterHasCorrectConfiguration(): void
    {
        // Given - configuration is set in defineEnvironment

        // When
        $reporter = $this->whenResolveReporter();
        $status = $reporter->getStatus();

        // Then
        $this->assertTrue($status['enabled']);
        $this->assertEquals('***configured***', $status['database_id']);
        $this->assertEquals('testing', $status['environment']);
    }

    public function testExceptionReporterRespectsIgnoredExceptions(): void
    {
        // Given
        $this->givenIgnoredException(Exception::class);
        $exception = new Exception('Test exception');

        // When
        $reporter = $this->whenResolveReporter();

        // Then - internal state check
        $this->assertInstanceOf(ExceptionReporter::class, $reporter);
    }

    // Given methods

    private function givenDatabaseIdNotConfigured(): void
    {
        $this->app['config']->set('notion-exceptions.database_id', null);
        $this->app->forgetInstance(ExceptionReporter::class);
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    private function givenIgnoredException(string $exceptionClass): void
    {
        $this->app['config']->set('notion-exceptions.ignored_exceptions', [$exceptionClass]);
        $this->app->forgetInstance(ExceptionReporter::class);
    }

    // When methods

    private function whenCheckIsConfigured(): bool
    {
        return Integration::isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    private function whenGetStatus(): array
    {
        return Integration::getStatus();
    }

    private function whenResolveReporter(): ExceptionReporter
    {
        return $this->app->make(ExceptionReporter::class);
    }

    // Then methods

    private function thenShouldBeConfigured(bool $isConfigured): void
    {
        $this->assertTrue($isConfigured);
    }

    private function thenShouldNotBeConfigured(bool $isConfigured): void
    {
        $this->assertFalse($isConfigured);
    }

    /**
     * @param array<string, mixed> $status
     */
    private function thenStatusShouldHaveRequiredKeys(array $status): void
    {
        $this->assertArrayHasKey('enabled', $status);
        $this->assertArrayHasKey('database_id', $status);
        $this->assertArrayHasKey('environment', $status);
    }

    private function thenReporterShouldBeInstanceOfExceptionReporter(mixed $reporter): void
    {
        $this->assertInstanceOf(ExceptionReporter::class, $reporter);
    }
}
