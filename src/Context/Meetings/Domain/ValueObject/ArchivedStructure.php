<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ArchivedStructure
{
    /**
     * @param list<string> $ids
     */
    private function __construct(
        private array $ids,
    ) {
    }

    /**
     * @param list<Uuid> $ids
     */
    public static function of(array $ids): self
    {
        return new self(new ArrayCollection($ids)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues());
    }

    public function holds(Roster $roster): bool
    {
        $ids = new ArrayCollection($this->ids);

        return $ids->contains($roster->serviceId->toRfc4122()) || $ids->contains($roster->stageId?->toRfc4122());
    }
}
