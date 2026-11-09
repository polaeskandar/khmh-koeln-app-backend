<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;

final readonly class DisabledAccounts
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
        private Calendar $calendar,
        private Credentials $credentials,
    ) {
    }

    public function disable(string $email): void
    {
        $this->administration->whileLocked(function () use ($email): void {
            $account = $this->account($email);

            if ($this->lastAdministrator->is($account)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            $account->changeStatus($account->status()->disabledAt($this->calendar->now()));
            $this->accounts->save($account);
            $this->credentials->endAllOf($account->id());
        });
    }

    public function enable(string $email): void
    {
        $account = $this->account($email);

        $account->changeStatus($account->status()->enabled());
        $this->accounts->save($account);
    }

    private function account(string $email): Account
    {
        return $this->accounts->findByEmail($email) ?? throw NotFound::of('account');
    }
}
