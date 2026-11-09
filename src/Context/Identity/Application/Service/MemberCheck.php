<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class MemberCheck
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
    ) {
    }

    public function require(string $accountId): Uuid
    {
        $account = $this->accounts->find(Uuid::fromString($accountId));
        $personId = $account?->status()->isActive() === true ? $account->member()->personId : null;

        return $personId ?? throw new AccessDeniedHttpException('forbidden');
    }
}
