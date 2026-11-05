<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface GuardianRelationshipRepositoryInterface
{
    public function save(GuardianRelationship $relationship): void;

    public function find(Uuid $id): ?GuardianRelationship;

    /**
     * @param positive-int $limit
     *
     * @return ReadableCollection<int, GuardianRelationship>
     */
    public function approved(int $limit): ReadableCollection;

    public function approvedFor(Uuid $accountId, Person $child): ?GuardianRelationship;

    /**
     * @return ReadableCollection<int, GuardianRelationship>
     */
    public function approvedOf(Uuid $accountId): ReadableCollection;

    /**
     * @param list<Uuid> $childIds
     *
     * @return ReadableCollection<int, GuardianRelationship>
     */
    public function approvedForChildren(array $childIds): ReadableCollection;

    /**
     * @return ReadableCollection<int, GuardianRelationship>
     */
    public function allForChild(Person $child): ReadableCollection;

    /**
     * @return ReadableCollection<int, GuardianRelationship>
     */
    public function allOfAccount(Uuid $accountId): ReadableCollection;
}
