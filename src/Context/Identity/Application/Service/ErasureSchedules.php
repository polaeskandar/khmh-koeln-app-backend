<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;
use App\Platform\Access\Contract\Administration;
use Symfony\Component\Uid\Uuid;

final readonly class ErasureSchedules
{
    public function __construct(
        private UserRepositoryInterface $users,
        private Administration $administration,
        private ApiTokens $apiTokens,
    ) {
    }

    public function schedule(Uuid $accountId, LocalDate $erasesOn): void
    {
        $this->administration->whileLocked(function () use ($accountId, $erasesOn): void {
            $this->users->withWriteLock($accountId, function (User $user) use ($erasesOn): void {
                if ($this->administration->isLastAdministrator($user->id())) {
                    throw new RuleViolation('roles.lastAdministrator');
                }

                $user->changeErasure($erasesOn);
            });

            $this->apiTokens->revokeAllOf($accountId);
        });
    }

    public function cancel(Uuid $accountId): void
    {
        $this->users->withWriteLock($accountId, static function (User $user): void {
            $user->changeErasure(null);
        });
    }
}
