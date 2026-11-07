<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use App\Context\Identity\Domain\Entity\PasswordToken;
use App\Context\Identity\Domain\Entity\User;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface PasswordTokenRepositoryInterface
{
    public function replaceUnused(#[\SensitiveParameter] PasswordToken $token): void;

    public function forgetUnusedOf(Uuid $userId): void;

    /**
     * @param callable(User): void $use
     */
    public function spend(#[\SensitiveParameter] string $tokenHash, DateTimeImmutable $at, callable $use): bool;
}
