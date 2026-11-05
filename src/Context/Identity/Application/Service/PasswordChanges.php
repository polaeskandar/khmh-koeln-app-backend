<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
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
        private ApiTokens $apiTokens,
    ) {
    }

    public function fromLink(#[SensitiveParameter] string $token, #[SensitiveParameter] string $password): bool
    {
        $passwordHash = $this->hasher->hash(PlainPassword::of($password));

        return $this->tokens->spend(Checksum::sha256($token), $this->calendar->now(), function (User $user) use (
            $passwordHash,
        ): void {
            $user->changePassword($passwordHash);
            $this->apiTokens->revokeAllOf($user->id());
        });
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
            $this->apiTokens->revokeAllOf($user->id());
            $changed = true;
        });

        return $changed;
    }
}
