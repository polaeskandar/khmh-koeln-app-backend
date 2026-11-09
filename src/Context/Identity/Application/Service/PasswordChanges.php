<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Library\Checksum\Model\Checksum;
use App\Library\Time\Client\Calendar;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class PasswordChanges
{
    public function __construct(
        private PasswordTokenRepositoryInterface $tokens,
        private AccountRepositoryInterface $accounts,
        private PasswordHasherInterface $hasher,
        private Calendar $calendar,
        private ApiTokens $apiTokens,
    ) {
    }

    public function fromLink(#[SensitiveParameter] string $token, #[SensitiveParameter] string $password): bool
    {
        $plain = PlainPassword::of($password);

        return $this->tokens->spend(Checksum::sha256($token), $this->calendar->now(), function (Account $account) use (
            $plain,
        ): void {
            $account->changePassword($this->hasher->hash($plain));
            $this->apiTokens->revokeAllOf($account->id());
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

        $this->accounts->withWriteLock($accountId, function (Account $account) use (
            $current,
            $password,
            &$changed,
        ): void {
            if (!$this->hasher->matches($account->passwordHash(), $current)) {
                return;
            }

            $account->changePassword($this->hasher->hash($password));
            $this->apiTokens->revokeAllOf($account->id());
            $changed = true;
        });

        return $changed;
    }
}
