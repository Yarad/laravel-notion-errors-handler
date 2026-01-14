<?php

declare(strict_types=1);

namespace Yarad\NotionExceptionHandler\Notion\Content;

use Notion\Blocks\BlockInterface;
use Notion\Blocks\BulletedListItem;
use Notion\Blocks\Heading3;

class ContextFormatter
{
    /**
     * Format context array into Notion blocks.
     *
     * @param array<string, mixed> $context
     *
     * @return array<BlockInterface>
     */
    public function format(array $context): array
    {
        if (empty($context)) {
            return [];
        }

        $blocks = [];

        foreach ($context as $category => $data) {
            if (is_array($data)) {
                // Skip empty arrays
                if (empty($data)) {
                    continue;
                }

                // Category heading
                $blocks[] = Heading3::fromString(ucfirst($category));

                // Items as bulleted list
                foreach ($data as $key => $value) {
                    $formattedKey = ucfirst(str_replace('_', ' ', (string) $key));
                    $blocks[] = BulletedListItem::fromString("{$formattedKey}: {$value}");
                }
            } else {
                // Scalar value as bulleted list item
                $formattedKey = ucfirst(str_replace('_', ' ', $category));
                $blocks[] = BulletedListItem::fromString("{$formattedKey}: {$data}");
            }
        }

        return $blocks;
    }
}
