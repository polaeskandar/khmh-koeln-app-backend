<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\Placement;
use App\Context\People\Domain\ValueObject\Tenure;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
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

    public function add(string $personId, Placement $placement, string $placeName): Membership
    {
        $found = Uuid::isValid($personId) ? $this->people->find(Uuid::fromString($personId)) : null;
        $person = $found ?? throw NotFound::of('person');
        $today = $this->calendar->today();

        if ($this->memberships->hasCurrent($person, $placement->serviceId, $placement->stageId, $today)) {
            throw self::alreadyIn($person, $placeName);
        }

        $membership = new Membership($person, $placement->serviceId, $placement->stageId, Tenure::from($today));

        try {
            $this->memberships->save($membership);
        } catch (UniqueConstraintViolationException) {
            throw self::alreadyIn($person, $placeName);
        }

        return $membership;
    }

    private static function alreadyIn(Person $person, string $placeName): RuleViolation
    {
        $name = $person->name();

        return new RuleViolation('people.alreadyIn', [
            'name' => Text::format('%s %s', $name->firstName, $name->lastName)->toString(),
            'where' => $placeName,
        ]);
    }
}
