<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface PersonRepositoryInterface
{
    public function save(Person $person, Membership ...$memberships): void;

    public function find(Uuid $id): ?Person;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Person>
     */
    public function findByIds(array $ids): ReadableCollection;

    /**
     * @param list<string> $words
     *
     * @return ReadableCollection<int, Person>
     */
    public function searchActive(array $words, int $limit): ReadableCollection;

    /**
     * @param ReadableCollection<int, Person> $people
     *
     * @return ReadableCollection<int, Membership>
     */
    public function membershipsOf(ReadableCollection $people): ReadableCollection;
}
