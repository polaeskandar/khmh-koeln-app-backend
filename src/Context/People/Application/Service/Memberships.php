<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\MembershipEnding;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\Placement;
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
        $person = $this->people->find(Uuid::fromString($personId)) ?? throw NotFound::of('person');
        $today = $this->calendar->today();

        if ($this->memberships->hasCurrent($person, $placement->serviceId, $placement->stageId, $today)) {
            throw self::alreadyIn($person, $placeName);
        }

        $membership = new Membership($person, $placement->serviceId, $placement->stageId, $today);

        try {
            $this->memberships->save($membership);
        } catch (UniqueConstraintViolationException) {
            throw self::alreadyIn($person, $placeName);
        }

        return $membership;
    }

    public function end(string $membershipId): void
    {
        $today = $this->calendar->today();

        $this->memberships->withWriteLock(Uuid::fromString($membershipId), function (Membership $membership) use (
            $today,
        ): void {
            if ($membership->endFrom($today) === MembershipEnding::DeleteInstead) {
                $this->memberships->remove($membership);

                return;
            }

            $this->memberships->save($membership);
        });
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
