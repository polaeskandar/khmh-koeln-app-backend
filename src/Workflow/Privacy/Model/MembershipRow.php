<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Domain\Entity\Membership;
use App\Library\Collections\Model\Collections;
use App\Workflow\People\Model\Placements;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class MembershipRow
{
    public function __construct(
        public ?string $service,
        public ?string $stage,
        public string $from,
        public ?string $until,
    ) {
    }

    /**
     * @param ReadableCollection<int, Membership> $memberships
     *
     * @return list<self>
     */
    public static function listOf(ReadableCollection $memberships, StructureLookup $structure): array
    {
        $placements = Placements::lookedUpIn($structure, $memberships);

        $rowOf = static function (Membership $membership) use ($placements): self {
            $group = $placements->groupOf($membership);
            $tenure = $membership->tenure();

            return new self(
                $group?->service->name,
                $group?->stage?->name,
                $tenure->startsOn->toString(),
                $tenure->endsOn?->toString(),
            );
        };

        return new ArrayCollection(Collections::sortedBy($memberships, keyOf: self::sortKey(...)))->map(
            $rowOf,
        )->getValues();
    }

    private static function sortKey(Membership $membership): string
    {
        return $membership->tenure()->startsOn->toString() . ' ' . $membership->id()->toRfc4122();
    }
}
