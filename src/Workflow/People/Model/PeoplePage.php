<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

final readonly class PeoplePage
{
    /**
     * @param list<PersonRow> $items
     */
    public function __construct(
        public string $today,
        public array $items,
        public PageInfo $pages,
        public bool $canAdd,
    ) {
    }
}
