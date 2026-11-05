<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountRequests;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\AccountRequest;
use App\Workflow\Identity\Model\AccountRequestList;
use App\Workflow\Identity\Model\RoleSummary;
use Symfony\Component\Uid\Uuid;

final readonly class ListAccountRequestsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountRequests $requests,
        private RegistrationServices $registrations,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(): AccountRequestList
    {
        $this->access->require(Action::Read, Resource::Users, refusal: 'accounts.adminOnly');

        $pending = $this->requests->pending();

        $chosen = $this->registrations->chosenBy(
            $pending->map(static fn (User $user): Uuid => $user->id())->getValues(),
        );

        return new AccountRequestList(
            items: $pending->map(fn (User $user): AccountRequest => AccountRequest::of(
                $user,
                $chosen,
                $this->calendar,
            ))->getValues(),
            roles: $this->requests
                ->roleChoices()
                ->map(RoleSummary::of(...))
                ->getValues(),
            today: $this->calendar->today()->toString(),
        );
    }
}
