<?php

declare(strict_types=1);

namespace App\Platform\Access\Model;

use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Group
{
    public function __construct(
        public Uuid $serviceId,
        public ?Uuid $stageId,
    ) {
    }

    public function covers(self $group): bool
    {
        if (!$this->serviceId->equals($group->serviceId)) {
            return false;
        }

        return $this->stageId === null || $group->stageId !== null && $this->stageId->equals($group->stageId);
    }

    /**
     * @param ReadableCollection<int, self> $held
     */
    public function isIn(ReadableCollection $held): bool
    {
        return !$held->filter(fn (self $group): bool => $group->covers($this))->isEmpty();
    }

    public function isServiceWide(): bool
    {
        return $this->stageId === null;
    }
}
