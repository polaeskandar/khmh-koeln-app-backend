<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\AlreadyIn;
use App\Context\People\Domain\ValueObject\Placement;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class MembershipMoves
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private MembershipRepositoryInterface $memberships,
        private Calendar $calendar,
    ) {
    }

    /**
     * @template T of object
     *
     * @param callable(Membership): T $work
     *
     * @return T
     */
    public function whileMovable(string $membershipId, callable $work): object
    {
        $id = Uuid::fromString($membershipId);
        $personId = $this->memberships->personOf($id) ?? throw NotFound::of('membership');
        $moved = null;

        $this->people->withWriteLock($personId, function () use ($id, $work, &$moved): void {
            $this->memberships->withWriteLock($id, static function (Membership $membership) use ($work, &$moved): void {
                if ($membership->tenure()->endsOn !== null) {
                    throw new RuleViolation('people.membershipEnded');
                }

                $moved = $work($membership);
            });
        });

        return $moved ?? throw new LogicException('The membership was not moved.');
    }

    public function move(Membership $membership, Placement $target, string $placeName): Membership
    {
        if ($target->stageId?->toRfc4122() === $membership->stageId()?->toRfc4122()) {
            return $membership;
        }

        $today = $this->calendar->today();

        if ($this->memberships->hasCurrent($membership->person(), $target->serviceId, $target->stageId, $today)) {
            throw AlreadyIn::of($membership->person(), $placeName);
        }

        $current = $membership->moveTo($target->stageId, $today);

        try {
            $this->memberships->save($membership);
            $this->memberships->save($current);
        } catch (UniqueConstraintViolationException) {
            throw AlreadyIn::of($membership->person(), $placeName);
        }

        return $current;
    }
}
