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
        return $this->transactions->run(fn (): PersonErased => $this->erased($personId, $elsewhere));
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

    /**
     * @param callable(ErasablePerson): ErasedElsewhere $elsewhere
     */
    private function erased(string $personId, callable $elsewhere): PersonErased
    {
        $id = Uuid::isValid($personId) ? Uuid::fromString($personId) : throw NotFound::of('person');
        $person = $this->people->findForUpdate($id) ?? throw NotFound::of('person');
        $swept = $elsewhere(new ErasablePerson($id, $this->people->membershipsOf(new ArrayCollection([$person]))));

        return new PersonErased($this->traces->forget($id), $swept);
    }
}
