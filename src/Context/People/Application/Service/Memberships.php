<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\AlreadyIn;
use App\Context\People\Domain\ValueObject\Placement;
use App\Context\People\Domain\ValueObject\Tenure;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class Memberships
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private MembershipRepositoryInterface $memberships,
        private Calendar $calendar,
    ) {
    }

    public function personOf(string $personId): Person
    {
        $found = Uuid::isValid($personId) ? $this->people->find(Uuid::fromString($personId)) : null;

        return $found ?? throw NotFound::of('person');
    }

    public function add(Person $person, Placement $placement, string $placeName): Membership
    {
        if ($person->status() !== PersonStatus::Active) {
            throw new RuleViolation('people.personInactive', ['name' => self::nameOf($person)]);
        }

        $today = $this->calendar->today();

        if ($this->memberships->hasCurrent($person, $placement->serviceId, $placement->stageId, $today)) {
            throw AlreadyIn::of($person, $placeName);
        }

        $membership = new Membership($person, $placement->serviceId, $placement->stageId, Tenure::from($today));

        try {
            $this->memberships->save($membership);
        } catch (UniqueConstraintViolationException) {
            throw AlreadyIn::of($person, $placeName);
        }

        return $membership;
    }

    private static function nameOf(Person $person): string
    {
        return $person->name()->fullName();
    }
}
