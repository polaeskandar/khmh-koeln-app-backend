<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Workflow\People\Model\PersonGroup;
use App\Workflow\People\Model\PersonReference;

final readonly class VisitorRow
{
    /**
     * @param list<PersonGroup> $placements
     */
    public function __construct(
        public PersonReference $person,
        public MarkReference $mark,
        public array $placements,
    ) {
    }
}
