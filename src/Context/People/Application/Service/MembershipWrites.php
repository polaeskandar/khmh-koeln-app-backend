<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\MembershipEnding;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\AlreadyIn;
use App\Context\People\Domain\ValueObject\AlreadyInService;
use App\Context\People\Domain\ValueObject\MembershipMove;
use App\Context\People\Domain\ValueObject\Placement;
use App\Context\People\Domain\ValueObject\RosterNames;
use App\Context\People\Domain\ValueObject\Tenure;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class MembershipWrites
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private MembershipRepositoryInterface $memberships,
        private Calendar $calendar,
    ) {
    }

    public function add(Person $person, Placement $placement, RosterNames $names): Membership
    {
        return $this->people->withWriteLock($person->id(), fn (Person $locked): Membership => $this->added(
            $locked,
            $placement,
            $names,
        ));
    }

    /**
     * @template T
     *
     * @param callable(Membership): T $work
     *
     * @return T
     */
    public function whileMovable(Uuid $membershipId, callable $work): mixed
    {
        $personId = $this->memberships->personOf($membershipId) ?? throw NotFound::of('membership');

        return $this->people->withWriteLock($personId, fn (): mixed => $this->memberships->withWriteLock(
            $membershipId,
            $work,
        ));
    }

    public function move(Membership $membership, Placement $target, string $placeName): Membership
    {
        if (MembershipMove::changesNothing($membership, $target)) {
            return $membership;
        }

        $today = $this->calendar->today();

        if ($this->memberships->hasCurrent($membership->person(), $target->serviceId, $target->stageId, $today)) {
            throw AlreadyIn::of($membership->person(), $placeName);
        }

        $current = MembershipMove::applied($membership, $target, $today);

        try {
            $this->memberships->save($membership);
            $this->memberships->save($current);
        } catch (UniqueConstraintViolationException) {
            throw AlreadyIn::of($membership->person(), $placeName);
        }

        return $current;
    }

    /**
     * @param callable(Membership): void $ensureAllowed
     */
    public function endById(Uuid $membershipId, callable $ensureAllowed): void
    {
        $today = $this->calendar->today();

        $this->memberships->withWriteLock($membershipId, function (Membership $membership) use (
            $today,
            $ensureAllowed,
        ): void {
            $ensureAllowed($membership);

            if (self::end($membership, $today) === MembershipEnding::DeleteInstead) {
                $this->memberships->remove($membership);

                return;
            }

            $this->memberships->save($membership);
        });
    }

    public static function end(Membership $membership, LocalDate $today): MembershipEnding
    {
        $ended = $membership->tenure()->endedFrom($today);

        if ($ended === null) {
            return MembershipEnding::DeleteInstead;
        }

        $membership->changeTenure($ended);

        return MembershipEnding::Ended;
    }

    private function added(Person $person, Placement $placement, RosterNames $names): Membership
    {
        if ($person->status() !== PersonStatus::Active) {
            throw new RuleViolation('people.personInactive', ['name' => $person->name()->fullName()]);
        }

        $today = $this->calendar->today();
        $this->ensureFree($person, $placement, $names, $today);
        $membership = new Membership($person, $placement->serviceId, $placement->stageId, Tenure::from($today));

        try {
            $this->memberships->save($membership);
        } catch (UniqueConstraintViolationException) {
            throw AlreadyIn::of($person, $names->of($placement->stageId));
        }

        return $membership;
    }

    private function ensureFree(Person $person, Placement $placement, RosterNames $names, LocalDate $on): void
    {
        if ($this->memberships->hasCurrent($person, $placement->serviceId, $placement->stageId, $on)) {
            throw AlreadyIn::of($person, $names->of($placement->stageId));
        }

        $otherLevel = $this->memberships->findAtOtherLevel($person, $placement, $on);

        if ($otherLevel !== null) {
            throw AlreadyInService::of($person, $names->of($otherLevel->stageId()));
        }
    }
}
