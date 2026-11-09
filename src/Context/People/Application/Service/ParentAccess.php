<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianLink;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\GuardianLinkRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ParentAccess
{
    public function __construct(
        private GuardianLinkRepositoryInterface $links,
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param positive-int $limit
     *
     * @return ReadableCollection<int, GuardianLink>
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

    public function revoke(string $id, string $accountId): void
    {
        $link = (Uuid::isValid($id) ? $this->links->find(Uuid::fromString($id)) : null) ?? throw NotFound::of(
            'guardian',
        );

        if ($link->revokedAt() !== null) {
            return;
        }

        $link->revoke($this->calendar->now(), by: Uuid::fromString($accountId));
        $this->links->save($link);
    }
}
