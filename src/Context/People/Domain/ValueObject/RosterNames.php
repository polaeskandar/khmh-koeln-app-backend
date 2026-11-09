<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class RosterNames
{
    /**
     * @param ReadableCollection<non-empty-string, string> $stageNames
     */
    public function __construct(
        private string $serviceName,
        private ReadableCollection $stageNames,
    ) {
    }

    public function of(?Uuid $stageId): string
    {
        if ($stageId === null) {
            return $this->serviceName;
        }

        return $this->stageNames->get($stageId->toRfc4122()) ?? $this->serviceName;
    }
}
