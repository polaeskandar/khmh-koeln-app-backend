<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ForgottenPerson;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonErasures
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private RosterRepositoryInterface $rosters,
        private PersonTraceRepositoryInterface $traces,
    ) {
    }

    public function erasable(string $personId): ErasablePerson
    {
        $person = $this->people->findForUpdate(Uuid::fromString($personId)) ?? throw NotFound::of('person');

        return new ErasablePerson($person->id(), $this->people->membershipsOf(new ArrayCollection([$person])));
    }

    /**
     * @param list<RosterKey> $keys
     *
     * @return list<string>
     */
    public function rosteredOn(Uuid $personId, array $keys): array
    {
        return $this->rosters
            ->rostersOn($keys)
            ->filter(static fn (array $roster): bool => new ArrayCollection($roster)->map(
                static fn (Uuid $member): string => $member->toRfc4122(),
            )->contains($personId->toRfc4122()))
            ->getKeys();
    }

    public function forget(Uuid $personId): ForgottenPerson
    {
        return $this->traces->forget($personId);
    }
}
