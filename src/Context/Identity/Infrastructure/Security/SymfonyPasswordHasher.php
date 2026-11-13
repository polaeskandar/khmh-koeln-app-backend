<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use Override;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface as SymfonyHasher;

final readonly class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private PasswordHasherFactoryInterface $hashers,
    ) {
    }

    #[Override]
    public function hash(#[SensitiveParameter] PlainPassword $password): string
    {
        return $this->hasher()->hash($password->value);
    }

    #[Override]
    public function matches(string $hash, #[SensitiveParameter] string $password): bool
    {
        return $this->hasher()->verify($hash, $password);
    }

    private function hasher(): SymfonyHasher
    {
        return $this->hashers->getPasswordHasher(SecurityUser::class);
    }
}
