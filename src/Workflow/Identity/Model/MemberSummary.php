<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\People\Domain\Entity\Membership;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MemberSummary
{
    public function __construct(
        public string $personId,
        public int $groupCount,
    ) {
    }

    /**
     * @param ReadableCollection<int, Membership> $memberships
     * @param list<Uuid> $archivedIds
     */
    public static function of(Uuid $personId, ReadableCollection $memberships, array $archivedIds): self
    {
        $archived = new ArrayCollection($archivedIds)->map(static fn (Uuid $id): string => $id->toRfc4122());
        $open = $memberships->filter(static fn (Membership $membership): bool => self::isOpen($membership, $archived));

        return new self($personId->toRfc4122(), $open->count());
    }

    /**
     * @param ReadableCollection<int, string> $archived
     */
    private static function isOpen(Membership $membership, ReadableCollection $archived): bool
    {
        if ($archived->contains($membership->serviceId()->toRfc4122())) {
            return false;
        }

        return !$archived->contains($membership->stageId()?->toRfc4122());
    }
}
