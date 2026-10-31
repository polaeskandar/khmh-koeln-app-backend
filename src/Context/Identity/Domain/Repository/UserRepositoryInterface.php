<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\User;
use Symfony\Component\Uid\Uuid;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findByEmail(string $email): ?User;

    public function find(Uuid $id): ?User;
}
