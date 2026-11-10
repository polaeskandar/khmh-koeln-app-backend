<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Query;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface PersonQueryInterface
{
    public function find(Uuid $id): ?Person;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Person>
     */
    public function findByIds(array $ids): ReadableCollection;

    /**
     * @param ReadableCollection<int, Person> $people
     *
     * @return ReadableCollection<int, Membership>
     */
    public function listMembershipsOf(ReadableCollection $people): ReadableCollection;
}
