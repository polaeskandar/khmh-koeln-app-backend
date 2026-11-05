<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;

final readonly class DisabledAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
        private Calendar $calendar,
    ) {
    }

    public function disable(string $email): void
    {
        $this->administration->whileLocked(function () use ($email): void {
            $user = $this->account($email);

            if ($this->lastAdministrator->is($user)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            $user->changeStatus($user->status()->disabledAt($this->calendar->now()));
            $this->users->save($user);
        });
    }

    public function enable(string $email): void
    {
        $user = $this->account($email);

        $user->changeStatus($user->status()->enabled());
        $this->users->save($user);
    }

    private function account(string $email): User
    {
        return $this->users->findByEmail($email) ?? throw NotFound::of('account');
    }
}
