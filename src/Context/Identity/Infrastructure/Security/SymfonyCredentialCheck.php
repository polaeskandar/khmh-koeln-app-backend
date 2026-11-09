<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Interface\CredentialCheckInterface;
use Override;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Uid\Uuid;

final readonly class SymfonyCredentialCheck implements CredentialCheckInterface
{
    public function __construct(
        private AccountProvider $accounts,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Override]
    public function accountWith(string $email, #[SensitiveParameter] string $password): ?Uuid
    {
        try {
            $user = $this->accounts->loadUserByIdentifier($email);
        } catch (UserNotFoundException) {
            return null;
        }

        return $this->hasher->isPasswordValid($user, $password) ? Uuid::fromString($user->getUserIdentifier()) : null;
    }
}
