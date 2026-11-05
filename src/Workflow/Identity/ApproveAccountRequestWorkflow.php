<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountApprovals;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Context\People\Application\Service\ChildChoices;
use App\Context\People\Application\Service\Guardianship;
use App\Workflow\Identity\Model\AccountSummary;
use App\Workflow\Identity\Model\ApprovalChoice;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ApproveAccountRequestWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountApprovals $approvals,
        private ChildChoices $children,
        private Guardianship $guardianship,
        private RegistrationServices $registrations,
    ) {
    }

    public function __invoke(string $id, string $actorId, ApprovalChoice $choice): AccountSummary
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'accounts.adminOnly');

        $roles = $this->approvals->chosenRoles($choice->roleIds);
        $children = $choice->isParent() ? $this->children->of($choice->childIds) : new ArrayCollection();

        $approved = $this->approvals->approve($id, $actorId, $roles, function (User $user) use (
            $children,
            $actorId,
        ): void {
            foreach ($children as $child) {
                $this->guardianship->linkParent($user->id(), $child, approvedBy: $actorId);
            }

            $this->registrations->forget($user->id());
        });

        return AccountSummary::of($approved);
    }
}
