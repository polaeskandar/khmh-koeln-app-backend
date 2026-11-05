<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Library\Checksum\Model\Checksum;
use App\Library\Time\Client\Calendar;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class PasswordChanges
{
    public function __construct(
        private PasswordTokenRepositoryInterface $tokens,
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private Calendar $calendar,
    ) {
    }

    public function fromLink(#[SensitiveParameter] string $token, #[SensitiveParameter] string $password): bool
    {
        $plain = PlainPassword::of($password);

        return $this->tokens->spend(
            Checksum::sha256($token),
            $this->calendar->now(),
            fn (User $user) => $user->changePassword($this->hasher->hash($plain)),
        );
    }

    public function changeOwn(
        Uuid $accountId,
        #[SensitiveParameter]
        string $current,
        #[SensitiveParameter]
        string $new,
    ): bool {
        $password = PlainPassword::of($new, field: 'newPassword');
        $changed = false;

        $this->users->withWriteLock($accountId, function (User $user) use ($current, $password, &$changed): void {
            if (!$this->hasher->matches($user->passwordHash(), $current)) {
                return;
            }

            $user->changePassword($this->hasher->hash($password));
            $changed = true;
        });

        return $changed;
    }
}
