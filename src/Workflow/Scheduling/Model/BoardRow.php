<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Workflow\People\Model\PersonReference;

final readonly class BoardRow
{
    public function __construct(
        public PersonReference $person,
        public ?MarkReference $mark,
    ) {
    }
}
