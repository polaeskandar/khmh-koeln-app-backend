<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\MembershipEnding;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class PersonStatuses
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private MembershipRepositoryInterface $memberships,
        private Calendar $calendar,
    ) {
    }

    public function change(string $personId, PersonStatus $status): Person
    {
        $changed = null;

        $this->people->withWriteLock(Uuid::fromString($personId), function (Person $person) use (
            $status,
            &$changed,
        ): void {
            if ($person->status() !== $status) {
                $this->changeTo($person, $status);
            }

            $changed = $person;
        });

        return $changed ?? throw new LogicException('The person was not changed.');
    }

    private function changeTo(Person $person, PersonStatus $status): void
    {
        $person->changeStatus($status);
        $this->people->save($person);

        if ($status === PersonStatus::Inactive) {
            $this->endOpenMembershipsOf($person);
        }
    }

    private function endOpenMembershipsOf(Person $person): void
    {
        $today = $this->calendar->today();

        $open = $this->people
            ->membershipsOf(new ArrayCollection([$person]))
            ->filter(static fn (Membership $membership): bool => $membership->tenure()->endsOn === null);

        foreach ($open as $membership) {
            match ($membership->endFrom($today)) {
                MembershipEnding::DeleteInstead => $this->memberships->remove($membership),
                default => $this->memberships->save($membership),
            };
        }
    }
}
