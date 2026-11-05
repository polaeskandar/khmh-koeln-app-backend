<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class RosterBook
{
    /**
     * @param ReadableCollection<string, list<Uuid>> $rosters
     * @param ReadableCollection<string, Person> $people
     * @param ReadableCollection<int, GuardianRelationship> $links
     */
    public function __construct(
        private ReadableCollection $rosters,
        private ReadableCollection $people,
        private ReadableCollection $links,
    ) {
    }

    /**
     * @return list<Person>
     */
    public function activeMembersOf(RosterKey $key): array
    {
        return Collections::mapPresent(new ArrayCollection($this->rosters->get($key->id()) ?? []), $this->personOf(...))
            ->filter(static fn (Person $person): bool => $person->status() === PersonStatus::Active)
            ->getValues();
    }

    /**
     * @return list<Uuid> everyone in the book: the rosters' people and those looked up besides
     */
    public function personIds(): array
    {
        return $this->people->map(static fn (Person $person): Uuid => $person->id())->getValues();
    }

    public function personOf(Uuid $id): ?Person
    {
        return $this->people->get($id->toRfc4122());
    }

    /**
     * @return list<Uuid>
     */
    public function guardianAccountIdsOf(Person $child): array
    {
        return $this->links
            ->filter(static fn (GuardianRelationship $link): bool => $link->child()->id()->equals($child->id()))
            ->map(static fn (GuardianRelationship $link): Uuid => $link->accountId())
            ->getValues();
    }

    /**
     * @return list<Uuid>
     */
    public function guardianAccountIds(): array
    {
        return $this->links->map(static fn (GuardianRelationship $link): Uuid => $link->accountId())->getValues();
    }
}
