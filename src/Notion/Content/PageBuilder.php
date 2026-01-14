<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Notion\Content;

use DateTimeImmutable;
use Notion\Blocks\BlockInterface;
use Notion\Blocks\Code;
use Notion\Blocks\Divider;
use Notion\Blocks\Heading2;
use Notion\Blocks\Paragraph;
use Notion\Pages\Page;
use Notion\Pages\PageParent;
use Notion\Pages\Properties\Date;
use Notion\Pages\Properties\Number;
use Notion\Pages\Properties\RichTextProperty;
use Notion\Pages\Properties\Select;
use Notion\Pages\Properties\Title;
use Throwable;

class PageBuilder
{
    private readonly ContextFormatter $contextFormatter;

    /**
     * @param array<string, string> $fieldNames Field name mappings
     */
    public function __construct(
        private readonly array $fieldNames = [],
    ) {
        $this->contextFormatter = new ContextFormatter();
    }

    /**
     * Build a new page for an exception.
     *
     * @param string $databaseId The database ID
     * @param Throwable $exception The exception
     * @param string $fingerprint The exception fingerprint
     * @param string $environment The environment name
     *
     * @return Page
     */
    public function buildNewPage(
        string $databaseId,
        Throwable $exception,
        string $fingerprint,
        string $environment,
    ): Page {
        $now = new DateTimeImmutable();
        $parent = PageParent::database($databaseId);

        $title = $this->createTitle($exception);

        $page = Page::create($parent)
            ->addProperty($this->getFieldName('title'), Title::fromString($title))
            ->addProperty($this->getFieldName('first_seen'), Date::create($now))
            ->addProperty($this->getFieldName('last_seen'), Date::create($now))
            ->addProperty($this->getFieldName('occurrences'), Number::create(1))
            ->addProperty($this->getFieldName('environment'), Select::fromName($environment))
            ->addProperty($this->getFieldName('exception_class'), RichTextProperty::fromString(get_class($exception)))
            ->addProperty($this->getFieldName('file'), RichTextProperty::fromString($this->truncateFile($exception->getFile())))
            ->addProperty($this->getFieldName('line'), Number::create($exception->getLine()))
            ->addProperty($this->getFieldName('fingerprint'), RichTextProperty::fromString($fingerprint));

        return $page;
    }

    /**
     * Update an existing page with new occurrence.
     *
     * @param Page $page The existing page
     * @param int $currentOccurrences The current occurrence count
     *
     * @return Page
     */
    public function updatePageOccurrence(Page $page, int $currentOccurrences): Page
    {
        $now = new DateTimeImmutable();

        return $page
            ->addProperty($this->getFieldName('last_seen'), Date::create($now))
            ->addProperty($this->getFieldName('occurrences'), Number::create($currentOccurrences + 1));
    }

    /**
     * Build content blocks for the page.
     *
     * @param Throwable $exception The exception
     * @param array<string, mixed> $context The request context
     *
     * @return array<BlockInterface>
     */
    public function buildPageContent(Throwable $exception, array $context): array
    {
        $blocks = [];

        // Exception message
        $blocks[] = Heading2::fromString('Exception Message');
        $blocks[] = Paragraph::fromString($exception->getMessage() ?: 'No message');
        $blocks[] = Divider::create();

        // Stack trace
        $blocks[] = Heading2::fromString('Stack Trace');
        $blocks[] = $this->createStackTraceBlock($exception);
        $blocks[] = Divider::create();

        // Request context
        $contextBlocks = $this->contextFormatter->format($context);
        if (!empty($contextBlocks)) {
            $blocks[] = Heading2::fromString('Context');
            array_push($blocks, ...$contextBlocks);
        }

        return $blocks;
    }

    /**
     * Create a title for the exception.
     */
    protected function createTitle(Throwable $exception): string
    {
        $className = $this->getShortClassName($exception);
        $message = $exception->getMessage();

        // Truncate message if too long
        if (strlen($message) > 100) {
            $message = substr($message, 0, 97) . '...';
        }

        if (empty($message)) {
            return $className;
        }

        return "{$className}: {$message}";
    }

    /**
     * Get the short class name of the exception.
     */
    protected function getShortClassName(Throwable $exception): string
    {
        $className = get_class($exception);
        $parts = explode('\\', $className);

        return end($parts) ?: $className;
    }

    /**
     * Truncate file path to a reasonable length.
     */
    protected function truncateFile(string $file): string
    {
        // Remove base path if possible
        try {
            if (function_exists('base_path')) {
                $basePath = base_path();
                if (str_starts_with($file, $basePath)) {
                    $file = substr($file, strlen($basePath) + 1);
                }
            }
        } catch (\Throwable) {
            // Ignore - we'll just use the full path
        }

        // Truncate if still too long
        if (strlen($file) > 200) {
            $file = '...' . substr($file, -197);
        }

        return $file;
    }

    /**
     * Create a code block with the stack trace.
     */
    protected function createStackTraceBlock(Throwable $exception): Code
    {
        $trace = $exception->getTraceAsString();

        // Notion has a limit on block content, truncate if necessary
        $maxLength = 2000;
        if (strlen($trace) > $maxLength) {
            $trace = substr($trace, 0, $maxLength - 50) . "\n\n... (truncated)";
        }

        return Code::fromString($trace);
    }

    /**
     * Get the field name from config or use default.
     */
    public function getFieldName(string $key): string
    {
        $defaults = [
            'title' => 'Title',
            'first_seen' => 'First Seen',
            'last_seen' => 'Last Seen',
            'occurrences' => 'Occurrences',
            'environment' => 'Environment',
            'fingerprint' => 'Fingerprint',
            'exception_class' => 'Exception Class',
            'file' => 'File',
            'line' => 'Line',
        ];

        return $this->fieldNames[$key] ?? $defaults[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Get all field names.
     *
     * @return array<string, string>
     */
    public function getFieldNames(): array
    {
        return $this->fieldNames;
    }
}
