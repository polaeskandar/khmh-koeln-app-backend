<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\People\Domain\ValueObject\PersonErased;
use App\Library\Problem\Exception\NotFound;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonErasures
{
    public function __construct(
        private Transactions $transactions,
        private PersonRepositoryInterface $people,
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
        $person = $this->people->findForUpdate(Uuid::fromString($personId)) ?? throw NotFound::of('person');
        $erasable = new ErasablePerson($person->id(), $this->people->membershipsOf(new ArrayCollection([$person])));
        $swept = $elsewhere($erasable);

        return new PersonErased($this->traces->forget($person->id()), $swept);
    }
}
