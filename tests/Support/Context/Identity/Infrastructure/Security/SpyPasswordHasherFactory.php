<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Infrastructure\Security;

use Override;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherAwareInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class SpyPasswordHasherFactory implements PasswordHasherFactoryInterface
{
    /**
     * @var list<string>
     */
    private array $verifiedHashes = [];

    private int $hashCount = 0;

    public function __construct(
        private readonly PasswordHasherFactoryInterface $inner,
    ) {
    }

    #[Override]
    public function getPasswordHasher(string|PasswordAuthenticatedUserInterface|PasswordHasherAwareInterface $user): PasswordHasherInterface
    {
        return new SpyPasswordHasher(
            $this->inner->getPasswordHasher($user),
            $this->recordVerification(...),
            $this->recordHash(...),
        );
    }

    public function recordVerification(string $hash): void
    {
        $this->verifiedHashes[] = $hash;
    }

    /**
     * @return list<string>
     */
    public function verifiedHashes(): array
    {
        return $this->verifiedHashes;
    }

    public function recordHash(): void
    {
        $this->hashCount++;
    }

    public function hashCount(): int
    {
        return $this->hashCount;
    }
}
