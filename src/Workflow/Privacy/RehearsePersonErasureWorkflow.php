<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Privacy\Model\PersonErasureCounts;

final readonly class RehearsePersonErasureWorkflow
{
    public function __construct(
        private ErasePersonWorkflow $erasePerson,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(string $personId): PersonErasureCounts
    {
        return $this->transactions->rehearse(fn (): PersonErasureCounts => ($this->erasePerson)($personId));
    }
}
