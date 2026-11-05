<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\UnconfirmedAccounts;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class PurgeUnconfirmedAccountsWorkflow
{
    public function __construct(
        private UnconfirmedAccounts $accounts,
        private RegistrationServices $services,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(): int
    {
        return $this->transactions->run(function (): int {
            $purged = new ArrayCollection($this->accounts->purge());

            foreach ($purged as $accountId) {
                $this->services->forget($accountId);
            }

            return $purged->count();
        });
    }
}
