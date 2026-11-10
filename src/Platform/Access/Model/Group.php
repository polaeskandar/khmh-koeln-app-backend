<?php

declare(strict_types=1);

namespace App\Platform\Access\Model;

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

    public function isServiceWide(): bool
    {
        return $this->stageId === null;
    }
}
