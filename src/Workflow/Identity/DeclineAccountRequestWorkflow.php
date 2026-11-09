<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountRequests;
use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Platform\Doctrine\Contract\TransactionsInterface;

final readonly class DeclineAccountRequestWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountRequests $requests,
        private RegistrationServices $registrations,
        private TransactionsInterface $transactions,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Users, refusal: 'accounts.adminOnly');

        $this->transactions->run(fn () => $this->requests->whileUndecided($id, function (Account $account): void {
            $this->registrations->forget($account->id());
            $this->requests->decline($account);
        }));
    }
}
