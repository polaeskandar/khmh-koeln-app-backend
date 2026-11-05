<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\People\Domain\ValueObject\PersonErased;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonErasures
{
    public function __construct(
        private Transactions $transactions,
        private PersonRepositoryInterface $people,
        private RosterRepositoryInterface $rosters,
        private PersonTraceRepositoryInterface $traces,
    ) {
    }

    /**
     * @param callable(ErasablePerson): ErasedElsewhere $elsewhere
     */
    public function erase(string $personId, callable $elsewhere): PersonErased
    {
        $id = Uuid::isValid($personId) ? Uuid::fromString($personId) : throw NotFound::of('person');

        return $this->transactions->run(function () use ($id, $elsewhere): PersonErased {
            $swept = $elsewhere($this->erasable($id));

            return new PersonErased($this->traces->forget($id), $swept);
        });
    }

    public function erasable(Uuid $personId): ErasablePerson
    {
        $person = $this->people->findForUpdate($personId) ?? throw NotFound::of('person');
        $name = $person->name();

        return new ErasablePerson(
            $person->id(),
            Collections::mapPresent(
                new ArrayCollection([$name->firstName, $name->displayName]),
                static fn (?string $named): ?string => $named,
            )->getValues(),
            $this->people->membershipsOf(new ArrayCollection([$person])),
        );
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
}
