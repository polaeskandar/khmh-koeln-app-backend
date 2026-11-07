<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Privacy\Model\ErasureCounts;

final readonly class RehearseAccountErasureWorkflow
{
    public function __construct(
        private EraseAccountWorkflow $eraseAccount,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(string $accountId): ErasureCounts
    {
        return $this->transactions->rehearse(fn (): ErasureCounts => ($this->eraseAccount)($accountId));
    }
}
