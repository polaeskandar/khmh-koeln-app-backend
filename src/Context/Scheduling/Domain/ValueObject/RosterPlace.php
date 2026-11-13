<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class RosterPlace
{
    public function __construct(
        public Uuid $serviceId,
        public ?Uuid $stageId,
    ) {
    }

    public function holds(Roster $roster): bool
    {
        $stage = $roster->stageId?->toRfc4122();

        return $stage === $this->stageId?->toRfc4122() && $roster->serviceId->equals($this->serviceId);
    }

    public function isCoveredBy(Roster $roster): bool
    {
        if (!$roster->serviceId->equals($this->serviceId)) {
            return false;
        }

        return $roster->stageId === null || $this->holds($roster);
    }
}
