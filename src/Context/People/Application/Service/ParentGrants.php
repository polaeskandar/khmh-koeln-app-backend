<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianRelationship;
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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class ParentGrants
{
    public function __construct(
        private GuardianRelationshipRepositoryInterface $links,
        private PersonRepositoryInterface $people,
        private Transactions $transactions,
        private Calendar $calendar,
    ) {
    }

    public function child(Uuid $personId): Person
    {
        $child = $this->people->find($personId);

        if ($child === null) {
            throw NotFound::of('person');
        }

        if ($child->status() !== PersonStatus::Active) {
            throw InvalidInput::field('personId', 'accounts.childNotFound');
        }

        return $child;
    }

    /**
     * @param callable(): void $alongside
     */
    public function grant(AccessGrant $grant, Person $child, callable $alongside): GuardianRelationship
    {
        if ($this->links->approvedFor($grant->accountId, $child) !== null) {
            throw self::taken($grant, $child);
        }

        $link = new GuardianRelationship(
            $grant->accountId,
            $child,
            GuardianKind::from($grant->relationship),
            approvedAt: $this->calendar->now(),
            approvedBy: Uuid::fromString($grant->approvedBy),
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

    private static function taken(AccessGrant $grant, Person $child): RuleViolation
    {
        return new RuleViolation('guardians.alreadyHasAccess', [
            'parent' => $grant->parentName,
            'child' => $child->name()->fullName(),
        ]);
    }
}
