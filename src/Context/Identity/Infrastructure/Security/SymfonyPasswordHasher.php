<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Application\Service\PasswordHasherInterface;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use Override;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface as SymfonyHasher;
use Symfony\Component\String\ByteString;

final readonly class SymfonyPasswordHasher implements PasswordHasherInterface
{
    private const int UNUSABLE_SECRET_LENGTH = 64;

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
    public function unusableHash(): string
    {
        return $this->hasher()->hash(ByteString::fromRandom(self::UNUSABLE_SECRET_LENGTH)->toString());
    }

    private function hasher(): SymfonyHasher
    {
        return $this->hashers->getPasswordHasher(SecurityUser::class);
    }
}
