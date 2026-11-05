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
     * @param callable(Person): void $work
     */
    public function withWriteLock(Uuid $id, callable $work): void;

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function withShareLock(Uuid $id, callable $work): mixed;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Person>
     */
    public function findByIds(array $ids): ReadableCollection;

    /**
     * @return ReadableCollection<int, Person>
     */
    public function withNameKey(string $key): ReadableCollection;

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
