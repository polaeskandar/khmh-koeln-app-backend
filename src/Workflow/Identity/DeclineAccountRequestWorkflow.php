<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLifecycle;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Library\Problem\Model\Ids;
use App\Platform\Doctrine\Contract\Transactions;

final readonly class DeclineAccountRequestWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountLifecycle $accounts,
        private RegistrationServices $registrations,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Users, refusal: 'accounts.adminOnly');

        $accountId = Ids::toUuid($id, notFound: 'user');
        $forgetRegistration = fn (User $user) => $this->registrations->forget($user->id());

        $this->transactions->run(fn () => $this->accounts->decline($accountId, $forgetRegistration));
    }
}
