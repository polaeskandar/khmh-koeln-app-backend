<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountRequests;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\RegistrationServices;

final readonly class DeclineAccountRequestWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountRequests $requests,
        private RegistrationServices $registrations,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Users, refusal: 'accounts.adminOnly');

        $this->requests->decide($id, function (User $user): void {
            $this->registrations->forget($user->id());
            $this->requests->decline($user);
        });
    }
}
