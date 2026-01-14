<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Notion;

use Notion\Databases\Database;
use Notion\Databases\Query;
use Notion\Databases\Query\Result;
use Notion\Notion;
use Notion\Pages\Page;

class NotionClient
{
    private Notion $notion;

    public function __construct(string $apiKey)
    {
        $this->notion = Notion::create($apiKey);
    }

    /**
     * Find a database by its ID.
     */
    public function findDatabase(string $databaseId): Database
    {
        return $this->notion->databases()->find($databaseId);
    }

    /**
     * Query a database with optional filters.
     */
    public function queryDatabase(Database $database, Query $query): Result
    {
        return $this->notion->databases()->query($database, $query);
    }

    /**
     * Create a new page in a database.
     *
     * @param Page $page The page to create
     * @param array<\Notion\Blocks\BlockInterface> $content The content blocks for the page
     */
    public function createPage(Page $page, array $content = []): Page
    {
        return $this->notion->pages()->create($page, $content);
    }

    /**
     * Update an existing page.
     */
    public function updatePage(Page $page): Page
    {
        return $this->notion->pages()->update($page);
    }

    /**
     * Find a page by its ID.
     */
    public function findPage(string $pageId): Page
    {
        return $this->notion->pages()->find($pageId);
    }

    /**
     * Get the underlying Notion SDK instance.
     */
    public function getNotion(): Notion
    {
        return $this->notion;
    }
}
