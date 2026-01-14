<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yarad\NotionExceptionHandler\Context\ContextCollectorInterface;
use Yarad\NotionExceptionHandler\Context\RequestContextCollector;

class RequestContextCollectorTest extends TestCase
{
    public function testImplementsContextCollectorInterface(): void
    {
        // Given
        $collector = $this->givenCollectorWithConfig([]);

        // Then
        $this->assertInstanceOf(ContextCollectorInterface::class, $collector);
    }

    public function testCollectReturnsEmptyArrayWhenNoRequestAvailable(): void
    {
        // Given - in CLI mode, there's no request
        $collector = $this->givenCollectorWithConfig([
            'request' => true,
            'headers' => true,
            'user' => true,
        ]);

        // When
        $context = $this->whenCollect($collector);

        // Then - should return empty array when no request is available
        $this->assertIsArray($context);
    }

    public function testGetConfigReturnsProvidedConfig(): void
    {
        // Given
        $config = [
            'request' => true,
            'headers' => false,
            'user' => true,
        ];
        $collector = $this->givenCollectorWithConfig($config);

        // When
        $returnedConfig = $this->whenGetConfig($collector);

        // Then
        $this->assertEquals($config, $returnedConfig);
    }

    public function testCollectorWithEmptyConfigReturnsEmptyContext(): void
    {
        // Given
        $collector = $this->givenCollectorWithConfig([]);

        // When
        $context = $this->whenCollect($collector);

        // Then
        $this->assertIsArray($context);
        $this->assertEmpty($context);
    }

    public function testCollectorRespectsIndividualCategorySettings(): void
    {
        // Given
        $collector = $this->givenCollectorWithConfig([
            'request' => true,
            'headers' => false,
            'user' => false,
        ]);

        // When
        $config = $this->whenGetConfig($collector);

        // Then
        $this->assertTrue($config['request']);
        $this->assertFalse($config['headers']);
        $this->assertFalse($config['user']);
    }

    // Given methods

    /**
     * @param array<string, bool> $config
     */
    private function givenCollectorWithConfig(array $config): RequestContextCollector
    {
        return new RequestContextCollector($config);
    }

    // When methods

    /**
     * @return array<string, mixed>
     */
    private function whenCollect(RequestContextCollector $collector): array
    {
        return $collector->collect();
    }

    /**
     * @return array<string, bool>
     */
    private function whenGetConfig(RequestContextCollector $collector): array
    {
        return $collector->getConfig();
    }
}
