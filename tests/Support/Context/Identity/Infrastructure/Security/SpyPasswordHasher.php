<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Infrastructure\Security;

use Closure;
use Override;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final readonly class SpyPasswordHasher implements PasswordHasherInterface
{
    /**
     * @param Closure(string): void $recordVerification
     */
    public function __construct(
        private PasswordHasherInterface $inner,
        private Closure $recordVerification,
    ) {
    }

    #[Override]
    public function hash(#[SensitiveParameter] string $plainPassword): string
    {
        return $this->inner->hash($plainPassword);
    }

    #[Override]
    public function verify(
        #[SensitiveParameter]
        string $hashedPassword,
        #[SensitiveParameter]
        string $plainPassword,
    ): bool {
        ($this->recordVerification)($hashedPassword);

        return $this->inner->verify($hashedPassword, $plainPassword);
    }

    #[Override]
    public function needsRehash(#[SensitiveParameter] string $hashedPassword): bool
    {
        return $this->inner->needsRehash($hashedPassword);
    }
}
