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

    public function covers(Uuid $serviceId, ?Uuid $stageId): bool
    {
        if (!$this->serviceId->equals($serviceId)) {
            return false;
        }

        return $this->stageId === null || $stageId !== null && $this->stageId->equals($stageId);
    }

    public function isServiceWide(): bool
    {
        return $this->stageId === null;
    }
}
