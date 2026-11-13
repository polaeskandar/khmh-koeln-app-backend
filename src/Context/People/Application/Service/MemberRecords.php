<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\Family;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MemberRecords
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    public function getOwnRecord(Uuid $personId): Family
    {
        $today = $this->calendar->today();
        $person = $this->people->find($personId);
        $own = new ArrayCollection($person?->status() === PersonStatus::Active ? [$person] : []);

        $current = $this->people
            ->listMembershipsOf($own)
            ->filter(static fn (Membership $membership): bool => $membership->tenure()->includes($today));

        return new Family($today, $own->getValues(), $current);
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function whilePersonKept(Uuid $personId, callable $work): mixed
    {
        return $this->people->withShareLock($personId, $work);
    }
}
