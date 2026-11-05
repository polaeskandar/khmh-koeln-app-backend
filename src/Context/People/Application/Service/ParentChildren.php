<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\Family;
use App\Library\Time\Client\Calendar;
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
            ->filter(static fn (Membership $membership): bool => $membership->tenure()->includes($today));

        return new Family($today, $children->getValues(), $current);
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
