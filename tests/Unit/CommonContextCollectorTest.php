<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yarad\NotionExceptionHandler\Context\CommonContextCollector;
use Yarad\NotionExceptionHandler\Context\ConsoleContextCollector;
use Yarad\NotionExceptionHandler\Context\ContextCollectorInterface;
use Yarad\NotionExceptionHandler\Context\RequestContextCollector;

class CommonContextCollectorTest extends TestCase
{
    public function testImplementsContextCollectorInterface(): void
    {
        // Given
        $collector = $this->givenCommonCollector();

        // Then
        $this->assertInstanceOf(ContextCollectorInterface::class, $collector);
    }

    public function testCollectReturnsConsoleContextInCliMode(): void
    {
        // Given - we're running in CLI mode (phpunit)
        $collector = $this->givenCommonCollector();

        // When
        $context = $this->whenCollect($collector);

        // Then - should delegate to ConsoleContextCollector
        $this->assertArrayHasKey('type', $context);
        $this->assertEquals('console', $context['type']);
    }

    public function testDelegatesToConsoleCollectorWhenNotHttp(): void
    {
        // Given
        $requestCollector = $this->createMock(RequestContextCollector::class);
        $requestCollector->expects($this->never())->method('collect');

        $consoleCollector = $this->createMock(ConsoleContextCollector::class);
        $consoleCollector->expects($this->once())
            ->method('collect')
            ->willReturn(['type' => 'console', 'command' => 'test']);

        $collector = new CommonContextCollector($requestCollector, $consoleCollector);

        // When
        $context = $collector->collect();

        // Then
        $this->assertEquals(['type' => 'console', 'command' => 'test'], $context);
    }

    // Given methods

    private function givenCommonCollector(): CommonContextCollector
    {
        $requestCollector = new RequestContextCollector([
            'request' => true,
            'headers' => true,
            'user' => true,
        ]);
        $consoleCollector = new ConsoleContextCollector();

        return new CommonContextCollector($requestCollector, $consoleCollector);
    }

    // When methods

    /**
     * @return array<string, mixed>
     */
    private function whenCollect(CommonContextCollector $collector): array
    {
        return $collector->collect();
    }
}
