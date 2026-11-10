<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\ServingAccounts;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Organization\Application\Service\AssignmentChanges;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Platform\Access\Model\Group;
use App\Workflow\Organization\Model\AssignmentView;

final readonly class AssignStaffWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServingAccounts $accounts,
        private PlacementChoices $placements,
        private AssignmentChanges $changes,
    ) {
    }

    public function __invoke(string $accountId, ?string $serviceId, ?string $stageId): AssignmentView
    {
        $this->access->requireAssigning();

        $account = $this->accounts->found($accountId);

        return $this->placements->whileKept([$serviceId], fn (): AssignmentView => $this->assigned(
            $account,
            $serviceId,
            $stageId,
        ));
    }

    private function assigned(User $account, ?string $serviceId, ?string $stageId): AssignmentView
    {
        $group = $this->placements->of($serviceId, $stageId, fieldPrefix: '');
        $this->access->requireAssigningIn(new Group($group->service->id(), $group->stage?->id()));

        return AssignmentView::of($this->changes->assign(
            $account->id(),
            $account->profile()->displayName->value,
            $group,
        ));
    }
}
