<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ParentAccess
{
    public function __construct(
        private GuardianRelationshipRepositoryInterface $links,
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param positive-int $limit
     *
     * @return ReadableCollection<int, GuardianRelationship>
     */
    public function approved(int $limit): ReadableCollection
    {
        return $this->links->approved($limit);
    }

    /**
     * @param ReadableCollection<int, Person> $children
     *
     * @return ReadableCollection<int, Membership>
     */
    public function currentMembershipsOf(ReadableCollection $children): ReadableCollection
    {
        $today = $this->calendar->today();

        return $this->people
            ->membershipsOf($children)
            ->filter(static fn (Membership $membership): bool => $membership->tenure()->includes($today));
    }

    public function revoke(Uuid $id, Uuid $accountId): void
    {
        $link = $this->links->find($id) ?? throw NotFound::of('guardian');

        if ($link->revokedAt() !== null) {
            return;
        }

        $link->revoke($this->calendar->now(), by: $accountId);
        $this->links->save($link);
    }
}
