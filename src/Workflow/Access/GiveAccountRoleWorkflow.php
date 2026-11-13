<?php

declare(strict_types=1);

namespace App\Workflow\Access;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\AccountRoles;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Library\Problem\Model\Ids;
use App\Workflow\Access\Model\RoleSummary;

final readonly class GiveAccountRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountRoles $roles,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $accountId, string $roleId): RoleSummary
    {
        $this->access->require(Action::Update, Resource::Roles, refusal: 'roles.adminOnly');

        $account = $this->accounts->get(Ids::toUuid($accountId, notFound: 'user'));

        return RoleSummary::of($this->roles->give(
            $account->id(),
            $account->username()->value,
            Ids::toUuidOrNull($roleId),
        ));
    }
}
