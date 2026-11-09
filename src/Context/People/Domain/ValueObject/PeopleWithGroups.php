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

final readonly class PeopleWithGroups
{
    /**
     * @param list<Person> $people
     * @param ReadableCollection<int, Membership> $currentMemberships
     */
    public function __construct(
        public LocalDate $today,
        public array $people,
        private ReadableCollection $currentMemberships,
    ) {
    }

    /**
     * @return ReadableCollection<int, Membership>
     */
    public function currentMemberships(): ReadableCollection
    {
        return $this->currentMemberships;
    }

    /**
     * @return ReadableCollection<int, Membership>
     */
    public function membershipsOf(Person $person): ReadableCollection
    {
        return $this->currentMemberships->filter(
            static fn (Membership $membership): bool => $membership->person() === $person,
        );
    }

    /**
     * @return list<Uuid>
     */
    public function stageIdsOf(Person $person): array
    {
        if ($person->status() !== PersonStatus::Active) {
            return [];
        }

        return Collections::mapPresent(
            $this->membershipsOf($person),
            static fn (Membership $membership): ?Uuid => $membership->stageId(),
        )->getValues();
    }
}
