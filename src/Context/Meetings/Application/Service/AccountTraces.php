<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Repository\AccountTraceRepositoryInterface;
use App\Platform\Privacy\Contract\AccountReferences;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class AccountTraces implements AccountReferences
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
