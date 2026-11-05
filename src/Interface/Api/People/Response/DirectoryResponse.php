<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Response;

use App\Workflow\People\Model\DirectoryEntry;

final readonly class DirectoryResponse
{
    /**
     * @param list<DirectoryEntry> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
