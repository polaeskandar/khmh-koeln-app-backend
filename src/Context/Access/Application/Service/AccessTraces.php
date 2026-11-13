<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Repository\AccountRoleRepositoryInterface;
use App\Platform\Privacy\Contract\AccountReferences;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class AccessTraces implements AccountReferences
{
    private const int NOTHING_KEPT = 0;

    public function __construct(
        private AccountRoleRepositoryInterface $accountRoles,
    ) {
    }

    #[Override]
    public function forget(Uuid $accountId): int
    {
        $this->accountRoles->forget($accountId);

        return self::NOTHING_KEPT;
    }
}
