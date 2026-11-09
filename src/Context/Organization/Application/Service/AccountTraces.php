<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Repository\AccountTraceRepositoryInterface;
use App\Platform\Privacy\Contract\AccountReferencesInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class AccountTraces implements AccountReferencesInterface
{
    public function __construct(
        private AccountTraceRepositoryInterface $traces,
    ) {
    }

    #[Override]
    public function forget(Uuid $accountId): int
    {
        return $this->traces->forget($accountId);
    }
}
