<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Family
{
    /**
     * @param list<Person> $children
     * @param ReadableCollection<int, Membership> $currentMemberships
     */
    public function __construct(
        public LocalDate $today,
        public array $children,
        private ReadableCollection $currentMemberships,
    ) {
    }

    /**
     * @return ReadableCollection<int, Membership>
     */
    public function membershipsOf(Person $child): ReadableCollection
    {
        return $this->currentMemberships->filter(
            static fn (Membership $membership): bool => $membership->person() === $child,
        );
    }

    /**
     * @return list<Uuid>
     */
    public function stageIdsOf(Person $child): array
    {
        if ($child->status() !== PersonStatus::Active) {
            return [];
        }

        return Collections::mapPresent(
            $this->membershipsOf($child),
            static fn (Membership $membership): ?Uuid => $membership->stageId(),
        )->getValues();
    }
}
