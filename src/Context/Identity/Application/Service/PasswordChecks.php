<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Interface\CredentialCheckInterface;
use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class PasswordChecks
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private CredentialCheckInterface $credentials,
    ) {
    }

    public function isOwn(Uuid $accountId, #[SensitiveParameter] string $password): bool
    {
        $user = $this->users->find($accountId);

        return $user !== null && $this->hasher->matches($user->passwordHash(), $password);
    }

    public function accountWith(string $email, #[SensitiveParameter] string $password): ?Uuid
    {
        return $this->credentials->accountWith($email, $password);
    }
}
