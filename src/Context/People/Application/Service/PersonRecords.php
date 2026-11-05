<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\Enrolment;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Context\People\Domain\ValueObject\Placement;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PersonRecords
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    public function nameOf(?string $firstName, ?string $lastName, ?string $displayName): PersonName
    {
        return PersonName::of($firstName ?? '', $lastName ?? '', $displayName);
    }

    /**
     * @return ReadableCollection<int, Person>
     */
    public function namesakesOf(PersonName $name): ReadableCollection
    {
        return $this->people->withNameKey($name->key);
    }

    public function create(PersonName $name, ?Placement $placement): Enrolment
    {
        $person = new Person($name->firstName, $name->lastName, $name->displayName);

        $membership = $placement === null
            ? null
            : new Membership($person, $placement->serviceId, $placement->stageId, $this->calendar->today());

        $this->people->save($person, ...$membership === null ? [] : [$membership]);

        return new Enrolment($person, $membership);
    }

    public function rename(string $id, ?string $firstName, ?string $lastName, ?string $displayName): Person
    {
        $person = $this->people->find(Uuid::fromString($id)) ?? throw NotFound::of('person');
        $name = $person->name();

        $person->rename(
            $firstName ?? $name->firstName,
            $lastName ?? $name->lastName,
            $displayName ?? $name->displayName,
        );

        $this->people->save($person);

        return $person;
    }
}
