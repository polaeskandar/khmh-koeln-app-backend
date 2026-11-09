<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface AccountRepositoryInterface
{
    public function save(Account $account): void;

    public function findByEmail(string $email): ?Account;

    public function find(Uuid $id): ?Account;

    public function remove(Account $account): void;

    /**
     * @param positive-int $limit
     *
     * @return ReadableCollection<int, Account>
     */
    public function pending(int $limit): ReadableCollection;

    /**
     * @param callable(Account): void $work
     */
    public function withWriteLock(Uuid $id, callable $work): void;

    public function hasOtherActiveAccountThatCan(Account $except, Action $action, Resource $resource): bool;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Account>
     */
    public function findByIds(array $ids): ReadableCollection;

    /**
     * @return ReadableCollection<int, Account>
     */
    public function activeThatCan(Action $action, Resource $resource): ReadableCollection;
}
