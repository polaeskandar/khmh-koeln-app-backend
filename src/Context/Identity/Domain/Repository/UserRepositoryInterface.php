<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findByEmail(string $email): ?User;

    public function find(Uuid $id): ?User;

    public function remove(User $user): void;

    /**
     * @param positive-int $limit
     *
     * @return ReadableCollection<int, User>
     */
    public function pending(int $limit): ReadableCollection;

    /**
     * @param callable(User): void $work
     */
    public function withWriteLock(Uuid $id, callable $work): void;

    public function hasOtherActiveAccountThatCan(User $except, Action $action, Resource $resource): bool;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, User>
     */
    public function findByIds(array $ids): ReadableCollection;

    /**
     * @return ReadableCollection<int, User>
     */
    public function activeThatCan(Action $action, Resource $resource): ReadableCollection;
}
