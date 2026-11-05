<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\AlreadyIn;
use App\Context\People\Domain\ValueObject\MembershipMove;
use App\Context\People\Domain\ValueObject\Placement;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
     * @template T
     *
     * @param callable(Membership): T $work
     *
     * @return T
     */
    public function whileMovable(string $membershipId, callable $work): mixed
    {
        $id = Uuid::isValid($membershipId) ? Uuid::fromString($membershipId) : throw NotFound::of('membership');
        $personId = $this->memberships->personOf($id) ?? throw NotFound::of('membership');

        return $this->people->withWriteLock($personId, fn (): mixed => $this->memberships->withWriteLock($id, $work));
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
}
