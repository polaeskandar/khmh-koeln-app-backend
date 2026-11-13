<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class PasswordChanges
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private ApiTokens $apiTokens,
        private PasswordChecks $checks,
    ) {
    }

    public function changeOwn(
        Uuid $accountId,
        #[SensitiveParameter]
        string $current,
        #[SensitiveParameter]
        string $new,
    ): void {
        $this->checks->ensureAttemptsLeft($accountId);
        $password = PlainPassword::of($new, field: 'newPassword');

        $this->users->withWriteLock($accountId, function (User $user) use ($current, $password): void {
            $this->checks->ensureMatches($user, $current, field: 'currentPassword');
            $user->changePassword($this->hasher->hash($password));
            $this->apiTokens->revokeAllOf($user->id());
        });
    }
}
