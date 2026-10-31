<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Entity\User;
use LogicException;
use Override;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    private function __construct(
        private string $id,
        private string $passwordHash,
    ) {
    }

    public static function of(User $user): self
    {
        return new self($user->id()->toRfc4122(), $user->passwordHash());
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * @return non-empty-string
     */
    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->id !== '' ? $this->id : throw new LogicException('An account always has an id.');
    }

    #[Override]
    public function getPassword(): string
    {
        return $this->passwordHash;
    }
}
