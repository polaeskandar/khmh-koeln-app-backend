<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\GuardianKind;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
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

    public function link(Uuid $accountId, Person $child, string $relationship): void
    {
        $kind = GuardianKind::tryFrom($relationship) ?? throw InvalidInput::field(
            'relationship',
            'validation.invalidChoice',
        );

        if ($this->links->approvedFor($accountId, $child) !== null) {
            return;
        }

        $this->links->save(
            new GuardianRelationship($accountId, $child, $kind, approvedAt: $this->calendar->now(), approvedBy: null),
        );
    }

    public function linkParent(Uuid $accountId, Person $child, string $approvedBy): void
    {
        $this->links->save(GuardianRelationship::approved(
            $accountId,
            $child,
            GuardianKind::Parent,
            $this->calendar->now(),
            by: Uuid::fromString($approvedBy),
        ));
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

    public function isGuardianOf(string $accountId, Person $child): bool
    {
        return Uuid::isValid($accountId) && $this->links->approvedFor(Uuid::fromString($accountId), $child) !== null;
    }
}
