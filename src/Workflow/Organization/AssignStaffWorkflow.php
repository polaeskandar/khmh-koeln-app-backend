<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\ServingAccounts;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Organization\Application\Service\AssignmentChanges;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\AssignmentView;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class AssignStaffWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServingAccounts $accounts,
        private PlacementChoices $placements,
        private AssignmentChanges $changes,
        private AccountLookup $lookup,
    ) {
    }

    public function __invoke(string $accountId, ?string $serviceId, ?string $stageId): AssignmentView
    {
        $this->access->requireAssigning();

        $account = $this->accounts->get(Ids::toUuid($accountId, notFound: 'user'));

        return $this->placements->whileServicesKept([$serviceId], fn (): AssignmentView => $this->assigned(
            $account,
            $serviceId,
            $stageId,
        ));
    }

    private function assigned(User $account, ?string $serviceId, ?string $stageId): AssignmentView
    {
        $group = $this->placements->chosen($serviceId, $stageId, fieldPrefix: '');
        $this->access->requireAssigningIn(StructureGroup::ofPlacement($group));

        return AssignmentView::of($this->changes->assign($account->id(), $this->lookup->nameOf($account), $group));
    }
}
