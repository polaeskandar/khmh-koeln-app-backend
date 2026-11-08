<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ChildHomework
{
    /**
     * @param ReadableCollection<int, Membership> $memberships
     */
    public function __construct(
        public Person $child,
        public ReadableCollection $memberships,
        public PupilTasks $tasks,
    ) {
    }
}
