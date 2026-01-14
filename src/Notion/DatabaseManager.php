<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Notion;

use Notion\Databases\Database;
use Notion\Databases\Query;
use Notion\Databases\Query\TextFilter;
use Notion\Pages\Page;
use Notion\Pages\Properties\Number;
use Throwable;
use Yarad\NotionExceptionHandler\Notion\Content\PageBuilder;

class DatabaseManager
{
    private ?Database $database = null;

    public function __construct(
        private readonly NotionClient $client,
        private readonly PageBuilder $pageBuilder,
    ) {
    }

    /**
     * Find or create an exception entry in the database.
     *
     * @param string $databaseId The database ID
     * @param Throwable $exception The exception
     * @param string $fingerprint The exception fingerprint
     * @param string $environment The environment name
     * @param array<string, mixed> $context The request context
     *
     * @return Page The created or updated page
     */
    public function recordException(
        string $databaseId,
        Throwable $exception,
        string $fingerprint,
        string $environment,
        array $context,
    ): Page {
        $existingPage = $this->findExistingException($databaseId, $fingerprint);

        if ($existingPage !== null) {
            return $this->updateExistingException($existingPage);
        }

        return $this->createNewException($databaseId, $exception, $fingerprint, $environment, $context);
    }

    /**
     * Find an existing exception by fingerprint.
     */
    public function findExistingException(string $databaseId, string $fingerprint): ?Page
    {
        $database = $this->getDatabase($databaseId);

        $query = Query::create()
            ->changeFilter(
                TextFilter::property($this->pageBuilder->getFieldNames()['fingerprint'] ?? 'Fingerprint')
                    ->equals($fingerprint),
            )
            ->changePageSize(1);

        $result = $this->client->queryDatabase($database, $query);

        if (count($result->pages) === 0) {
            return null;
        }

        return $result->pages[0];
    }

    /**
     * Create a new exception entry.
     *
     * @param array<string, mixed> $context
     */
    protected function createNewException(
        string $databaseId,
        Throwable $exception,
        string $fingerprint,
        string $environment,
        array $context,
    ): Page {
        $page = $this->pageBuilder->buildNewPage($databaseId, $exception, $fingerprint, $environment);
        $content = $this->pageBuilder->buildPageContent($exception, $context);

        return $this->client->createPage($page, $content);
    }

    /**
     * Update an existing exception entry.
     */
    protected function updateExistingException(Page $page): Page
    {
        $currentOccurrences = $this->getOccurrenceCount($page);
        $updatedPage = $this->pageBuilder->updatePageOccurrence($page, $currentOccurrences);

        return $this->client->updatePage($updatedPage);
    }

    /**
     * Get the current occurrence count from a page.
     */
    protected function getOccurrenceCount(Page $page): int
    {
        $fieldName = $this->pageBuilder->getFieldNames()['occurrences'] ?? 'Occurrences';
        $properties = $page->properties;

        foreach ($properties as $name => $property) {
            if ($name === $fieldName && $property instanceof Number) {
                return (int) ($property->number ?? 0);
            }
        }

        return 0;
    }

    /**
     * Get the database instance, caching it for reuse.
     */
    protected function getDatabase(string $databaseId): Database
    {
        if ($this->database === null || $this->database->id !== $databaseId) {
            $this->database = $this->client->findDatabase($databaseId);
        }

        return $this->database;
    }

    /**
     * Clear the cached database instance.
     */
    public function clearCache(): void
    {
        $this->database = null;
    }

    /**
     * Verify database connection and existence.
     *
     * @throws \RuntimeException If database cannot be accessed
     */
    public function verifyDatabase(string $databaseId): Database
    {
        try {
            return $this->getDatabase($databaseId);
        } catch (Throwable $e) {
            throw new \RuntimeException(
                "Unable to access Notion database '{$databaseId}': {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
