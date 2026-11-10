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
use Symfony\Component\Uid\Uuid;

final readonly class PersonStatuses
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private MembershipRepositoryInterface $memberships,
        private Calendar $calendar,
    ) {
    }

    public function change(Uuid $personId, PersonStatus $status): Person
    {
        return $this->people->withWriteLock($personId, function (Person $person) use ($status): Person {
            if ($person->status() !== $status) {
                $this->changeTo($person, $status);
            }

            return $person;
        });
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
            match (MembershipEndings::end($membership, $today)) {
                MembershipEnding::DeleteInstead => $this->memberships->remove($membership),
                MembershipEnding::Ended => $this->memberships->save($membership),
            };
        }
    }
}
