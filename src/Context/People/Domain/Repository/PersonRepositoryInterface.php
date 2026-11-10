<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Query\PersonQueryInterface;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface PersonRepositoryInterface extends PersonQueryInterface
{
    public function save(Person $person, Membership ...$memberships): void;

    /**
     * @template T
     *
     * @param callable(Person): T $work
     *
     * @return T
     */
    public function withWriteLock(Uuid $id, callable $work): mixed;

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function withShareLock(Uuid $id, callable $work): mixed;

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
}
