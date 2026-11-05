<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Library\Checksum\Model\Checksum;
use App\Library\Time\Client\Calendar;
use SensitiveParameter;

final readonly class PasswordChanges
{
    public function __construct(
        private PasswordTokenRepositoryInterface $tokens,
        private PasswordHasherInterface $hasher,
        private Calendar $calendar,
    ) {
    }

    public function fromLink(#[SensitiveParameter] string $token, #[SensitiveParameter] string $password): bool
    {
        $passwordHash = $this->hasher->hash(PlainPassword::of($password));

        return $this->tokens->spend(
            Checksum::sha256($token),
            $this->calendar->now(),
            static fn (User $user) => $user->changePassword($passwordHash),
        );
    }
}
