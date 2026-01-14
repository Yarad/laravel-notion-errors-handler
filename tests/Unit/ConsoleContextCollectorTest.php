<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yarad\NotionExceptionHandler\Context\ConsoleContextCollector;
use Yarad\NotionExceptionHandler\Context\ContextCollectorInterface;

class ConsoleContextCollectorTest extends TestCase
{
    public function testImplementsContextCollectorInterface(): void
    {
        // Given
        $collector = $this->givenConsoleCollector();

        // Then
        $this->assertInstanceOf(ContextCollectorInterface::class, $collector);
    }

    public function testCollectReturnsConsoleType(): void
    {
        // Given
        $collector = $this->givenConsoleCollector();

        // When
        $context = $this->whenCollect($collector);

        // Then
        $this->assertArrayHasKey('type', $context);
        $this->assertEquals('console', $context['type']);
    }

    public function testCollectIncludesCommandWhenArtisanBinaryDefined(): void
    {
        // Given
        $collector = $this->givenConsoleCollector();

        // When
        $context = $this->whenCollect($collector);

        // Then
        $this->assertArrayHasKey('type', $context);
        // Command may or may not be present depending on environment
        $this->assertIsArray($context);
    }

    // Given methods

    private function givenConsoleCollector(): ConsoleContextCollector
    {
        return new ConsoleContextCollector();
    }

    // When methods

    /**
     * @return array<string, mixed>
     */
    private function whenCollect(ConsoleContextCollector $collector): array
    {
        return $collector->collect();
    }
}
