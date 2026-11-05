<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

interface MembershipRepositoryInterface
{
    public function save(Membership $membership): void;

    public function remove(Membership $membership): void;

    public function hasCurrent(Person $person, Uuid $serviceId, ?Uuid $stageId, LocalDate $on): bool;

    public function personOf(Uuid $membershipId): ?Uuid;

    /**
     * @param callable(Membership): void $work
     */
    public function withWriteLock(Uuid $id, callable $work): void;
}
