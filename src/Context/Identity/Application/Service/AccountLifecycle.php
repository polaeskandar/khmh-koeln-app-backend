<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use App\Platform\Access\Contract\Administration;

final readonly class AccountLifecycle
{
    public function __construct(
        private UserRepositoryInterface $users,
        private Administration $administration,
        private ApiTokens $apiTokens,
        private Calendar $calendar,
    ) {
    }

    public function disable(string $username): void
    {
        $this->administration->whileLocked(function () use ($username): void {
            $user = $this->getByUsername($username);

            if ($this->administration->isLastAdministrator($user->id())) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            $user->changeStatus($user->status()->disabledAt($this->calendar->now()));
            $this->users->save($user);
            $this->apiTokens->revokeAllOf($user->id());
        });
    }

    public function enable(string $username): void
    {
        $user = $this->getByUsername($username);

        $user->changeStatus($user->status()->enabled());
        $this->users->save($user);
    }

    private function getByUsername(string $username): User
    {
        return $this->users->findByUsername($username) ?? throw NotFound::of('account');
    }
}
