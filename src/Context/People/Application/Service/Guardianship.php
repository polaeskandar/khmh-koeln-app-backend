<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\GuardianKind;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class Guardianship
{
    public function __construct(
        private GuardianRelationshipRepositoryInterface $links,
        private Calendar $calendar,
    ) {
    }

    public function link(Uuid $accountId, Person $child, GuardianKind $kind): void
    {
        if ($this->links->approvedFor($accountId, $child) !== null) {
            return;
        }

        $this->links->save(
            new GuardianRelationship($accountId, $child, $kind, approvedAt: $this->calendar->now(), approvedBy: null),
        );
    }

    public function linkParent(Uuid $accountId, Person $child, Uuid $approvedBy): void
    {
        $this->links->save(
            new GuardianRelationship(
                $accountId,
                $child,
                GuardianKind::Parent,
                approvedAt: $this->calendar->now(),
                approvedBy: $approvedBy,
            ),
        );
    }

    public function revoke(Uuid $accountId, Person $child): void
    {
        $link = $this->links->approvedFor($accountId, $child);

        if ($link === null) {
            return;
        }

        $link->revoke($this->calendar->now(), by: null);
        $this->links->save($link);
    }

    /**
     * @return ReadableCollection<int, GuardianRelationship>
     */
    public function approvedOf(Uuid $accountId): ReadableCollection
    {
        return $this->links->approvedOf($accountId);
    }

    public function isGuardianOf(Uuid $accountId, Person $child): bool
    {
        return $this->links->approvedFor($accountId, $child) !== null;
    }
}
