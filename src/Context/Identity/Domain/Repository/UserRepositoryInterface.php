<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\User;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findByUsername(string $username): ?User;

    public function find(Uuid $id): ?User;

    public function remove(User $user): void;

    /**
     * @param callable(User): void $work
     */
    public function withWriteLock(Uuid $id, callable $work): void;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, User>
     */
    public function findByIds(array $ids): ReadableCollection;
}
