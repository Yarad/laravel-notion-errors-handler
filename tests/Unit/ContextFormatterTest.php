<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Tests\Unit;

use Notion\Blocks\BlockInterface;
use Notion\Blocks\BulletedListItem;
use Notion\Blocks\Heading3;
use PHPUnit\Framework\TestCase;
use Yarad\NotionExceptionHandler\Notion\Content\ContextFormatter;

class ContextFormatterTest extends TestCase
{
    public function testFormatReturnsEmptyArrayForEmptyContext(): void
    {
        // Given
        $formatter = $this->givenFormatter();

        // When
        $blocks = $this->whenFormat($formatter, []);

        // Then
        $this->assertIsArray($blocks);
        $this->assertEmpty($blocks);
    }

    public function testFormatReturnsBlocksForConsoleContext(): void
    {
        // Given
        $formatter = $this->givenFormatter();
        $context = [
            'type' => 'console',
            'command' => 'artisan test',
        ];

        // When
        $blocks = $this->whenFormat($formatter, $context);

        // Then
        $this->assertNotEmpty($blocks);
        $this->assertContainsOnlyInstancesOf(BlockInterface::class, $blocks);
    }

    public function testFormatCreatesHeadingForArrayCategories(): void
    {
        // Given
        $formatter = $this->givenFormatter();
        $context = [
            'request' => [
                'uri' => 'https://example.com/test',
                'method' => 'POST',
            ],
        ];

        // When
        $blocks = $this->whenFormat($formatter, $context);

        // Then
        $this->assertNotEmpty($blocks);
        $this->assertInstanceOf(Heading3::class, $blocks[0]);
    }

    public function testFormatCreatesBulletedListItemsForData(): void
    {
        // Given
        $formatter = $this->givenFormatter();
        $context = [
            'request' => [
                'uri' => 'https://example.com/test',
                'method' => 'POST',
            ],
        ];

        // When
        $blocks = $this->whenFormat($formatter, $context);

        // Then
        // First block is heading, next blocks are list items
        $this->assertInstanceOf(Heading3::class, $blocks[0]);
        $this->assertInstanceOf(BulletedListItem::class, $blocks[1]);
        $this->assertInstanceOf(BulletedListItem::class, $blocks[2]);
    }

    public function testFormatHandlesScalarValues(): void
    {
        // Given
        $formatter = $this->givenFormatter();
        $context = [
            'type' => 'console',
        ];

        // When
        $blocks = $this->whenFormat($formatter, $context);

        // Then
        $this->assertCount(1, $blocks);
        $this->assertInstanceOf(BulletedListItem::class, $blocks[0]);
    }

    public function testFormatSkipsEmptyArrays(): void
    {
        // Given
        $formatter = $this->givenFormatter();
        $context = [
            'request' => [],
            'type' => 'console',
        ];

        // When
        $blocks = $this->whenFormat($formatter, $context);

        // Then - only the scalar 'type' should create a block
        $this->assertCount(1, $blocks);
    }

    // Given methods

    private function givenFormatter(): ContextFormatter
    {
        return new ContextFormatter();
    }

    // When methods

    /**
     * @param array<string, mixed> $context
     *
     * @return array<BlockInterface>
     */
    private function whenFormat(ContextFormatter $formatter, array $context): array
    {
        return $formatter->format($context);
    }
}
