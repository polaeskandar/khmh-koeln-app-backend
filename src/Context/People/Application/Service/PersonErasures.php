<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\People\Domain\ValueObject\PersonErased;
use App\Library\Collections\Model\Collections;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ArrayCollection;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class PersonErasures
{
    public function __construct(
        private Transactions $transactions,
        private PersonRepositoryInterface $people,
        private GuardianRelationshipRepositoryInterface $links,
        private PersonTraceRepositoryInterface $traces,
    ) {
    }

    public function existing(?Uuid $personId): ?Uuid
    {
        return $personId === null ? null : $this->people->find($personId)?->id();
    }

    public function erasable(Uuid $personId): ErasablePerson
    {
        $person = null;

        $this->people->withWriteLock($personId, static function (Person $locked) use (&$person): void {
            $person = $locked;
        });

        $person ??= throw new LogicException('The person was not locked.');
        $name = $person->name();
        $names = Collections::mapPresent(new ArrayCollection([$name->firstName, $name->displayName]), self::named(...));

        return new ErasablePerson(
            $person->id(),
            $names->getValues(),
            $this->people->membershipsOf(new ArrayCollection([$person])),
        );
    }

    public function linksOfAccount(Uuid $accountId): int
    {
        return $this->links->allOfAccount($accountId)->count();
    }

    /**
     * @param callable(ErasablePerson): ErasedElsewhere $elsewhere
     */
    public function erase(string $personId, callable $elsewhere): PersonErased
    {
        return $this->transactions->run(fn (): PersonErased => $this->erased($personId, $elsewhere));
    }

    /**
     * @param callable(ErasablePerson): ErasedElsewhere $elsewhere
     */
    public function rehearse(string $personId, callable $elsewhere): PersonErased
    {
        return $this->transactions->rehearse(fn (): PersonErased => $this->erased($personId, $elsewhere));
    }

    /**
     * @param callable(ErasablePerson): ErasedElsewhere $elsewhere
     */
    private function erased(string $personId, callable $elsewhere): PersonErased
    {
        $person = $this->erasable(Uuid::fromString($personId));
        $swept = $elsewhere($person);

        return new PersonErased($this->traces->forget($person->id), $swept);
    }

    private static function named(?string $name): ?string
    {
        return $name;
    }
}
