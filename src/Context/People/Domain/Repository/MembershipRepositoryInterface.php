<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Query\MembershipQueryInterface;
use App\Context\People\Domain\ValueObject\Placement;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

interface MembershipRepositoryInterface extends MembershipQueryInterface
{
    public function save(Membership $membership): void;

    public function remove(Membership $membership): void;

    public function hasCurrent(Person $person, Uuid $serviceId, ?Uuid $stageId, LocalDate $on): bool;

    public function findAtOtherLevel(Person $person, Placement $placement, LocalDate $from): ?Membership;

    public function personOf(Uuid $membershipId): ?Uuid;

    /**
     * @template T
     *
     * @param callable(Membership): T $work
     *
     * @return T
     */
    public function withWriteLock(Uuid $id, callable $work): mixed;
}
