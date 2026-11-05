<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Context\People\Domain\Entity\Person;

final readonly class ChildHomework
{
    public function __construct(
        public Person $child,
        public PupilTasks $tasks,
    ) {
    }
}
