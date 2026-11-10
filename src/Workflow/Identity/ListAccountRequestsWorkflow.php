<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Query\RoleQueryInterface;
use App\Context\Identity\Domain\Query\UserQueryInterface;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\AccountRequest;
use App\Workflow\Identity\Model\AccountRequestList;
use App\Workflow\Identity\Model\RoleSummary;
use Symfony\Component\Uid\Uuid;

final readonly class ListAccountRequestsWorkflow
{
    private const int LISTED = 200;

    public function __construct(
        private AccessCheck $access,
        private UserQueryInterface $users,
        private RoleQueryInterface $roles,
        private RegistrationServices $registrations,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(): AccountRequestList
    {
        $this->access->require(Action::Read, Resource::Users, refusal: 'accounts.adminOnly');

        $pending = $this->users->listPending(self::LISTED);

        $chosen = $this->registrations->chosenBy(
            $pending->map(static fn (User $user): Uuid => $user->id())->getValues(),
        );

        return new AccountRequestList(
            items: $pending->map(
                static fn (User $user): AccountRequest => AccountRequest::of($user, $chosen),
            )->getValues(),
            roles: $this->roles
                ->listByName()
                ->map(RoleSummary::of(...))
                ->getValues(),
            today: $this->calendar->today()->toString(),
        );
    }
}
