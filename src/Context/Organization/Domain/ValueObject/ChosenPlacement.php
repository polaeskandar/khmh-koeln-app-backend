<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\ValueObject;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;

final readonly class ChosenPlacement
{
    public function __construct(
        public Service $service,
        public ?Stage $stage,
    ) {
    }

    public function isCoveredBy(self $group): bool
    {
        return $this->stage !== null && $group->stage === null && $this->service->id()->equals($group->service->id());
    }
}
