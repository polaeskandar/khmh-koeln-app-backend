<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\Family;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class ParentChildren
{
    public function __construct(
        private GuardianRelationshipRepositoryInterface $links,
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    public function of(Uuid $accountId): Family
    {
        $today = $this->calendar->today();

        $children = $this->links->approvedOf($accountId)->map(
            static fn (GuardianRelationship $link): Person => $link->child(),
        );

        $current = $this->people
            ->membershipsOf($children)
            ->filter(static fn (Membership $membership): bool => self::countsOn($membership, $today));

        return new Family($children->getValues(), $current);
    }

    private static function countsOn(Membership $membership, LocalDate $today): bool
    {
        return $membership->isCurrentOn($today) && $membership->person()->status() === PersonStatus::Active;
    }
}
