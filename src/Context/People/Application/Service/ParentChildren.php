<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianLink;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\GuardianLinkRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\PeopleWithGroups;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class ParentChildren
{
    public function __construct(
        private GuardianLinkRepositoryInterface $links,
        private PersonRepositoryInterface $people,
        private Calendar $calendar,
    ) {
    }

    public function ofParent(Uuid $accountId): PeopleWithGroups
    {
        $today = $this->calendar->today();

        $children = $this->links->approvedOf($accountId)->map(static fn (GuardianLink $link): Person => $link->child());

        $current = $this->people
            ->membershipsOf($children)
            ->filter(static fn (Membership $membership): bool => $membership->tenure()->includes($today));

        return new PeopleWithGroups($today, $children->getValues(), $current);
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function whileKept(string $childId, callable $work): mixed
    {
        return Uuid::isValid($childId) ? $this->people->withShareLock(Uuid::fromString($childId), $work) : $work();
    }
}
