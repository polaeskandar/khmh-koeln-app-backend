<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\GuardianKind;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\AccessGrant;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class ParentAccess
{
    public function __construct(
        private GuardianRelationshipRepositoryInterface $links,
        private PersonRepositoryInterface $people,
        private Transactions $transactions,
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
            ->filter(static fn (Membership $membership): bool => $membership->isCurrentOn($today));
    }

    public function child(string $personId): Person
    {
        $child = $this->people->find(Uuid::fromString($personId)) ?? throw NotFound::of('person');

        return $child->status() === PersonStatus::Active
            ? $child
            : throw InvalidInput::field('personId', 'accounts.childNotFound');
    }

    /**
     * @param callable(): void $alongside
     */
    public function grant(AccessGrant $grant, Person $child, callable $alongside): GuardianRelationship
    {
        if ($this->links->approvedFor($grant->accountId, $child) !== null) {
            throw self::taken($grant, $child);
        }

        $link = GuardianRelationship::approved(
            $grant->accountId,
            $child,
            GuardianKind::tryFrom($grant->relationship) ?? GuardianKind::Parent,
            $this->calendar->now(),
            by: Uuid::fromString($grant->approvedBy),
        );

        try {
            return $this->transactions->run(function () use ($link, $alongside): GuardianRelationship {
                $this->links->save($link);
                $alongside();

                return $link;
            });
        } catch (UniqueConstraintViolationException) {
            throw self::taken($grant, $child);
        }
    }

    public function revoke(string $id, string $accountId): void
    {
        $link = $this->links->find(Uuid::fromString($id)) ?? throw NotFound::of('guardian');
        $link->revoke($this->calendar->now(), by: Uuid::fromString($accountId));
        $this->links->save($link);
    }

    private static function taken(AccessGrant $grant, Person $child): RuleViolation
    {
        return new RuleViolation('guardians.alreadyHasAccess', [
            'parent' => $grant->parentName,
            'child' => $child->name()->full(),
        ]);
    }
}
