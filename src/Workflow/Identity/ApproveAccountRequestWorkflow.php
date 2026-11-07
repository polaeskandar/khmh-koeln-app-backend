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
use App\Library\Problem\Exception\InvalidInput;
use App\Workflow\Identity\Model\AccountSummary;
use App\Workflow\Identity\Model\ApprovalChoice;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

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
        $this->access->require(Action::Update, Resource::Roles, refusal: 'accounts.adminOnly');
        $this->approvals->ensurePending($id);

        $chooseRoles = $choice->isParent()
            ? fn (): ReadableCollection => $this->approvals->chosenParentRoles($choice->roleIds)
            : fn (): ReadableCollection => $this->approvals->chosenRoles($choice->roleIds);
        $chooseChildren = $choice->isParent()
            ? fn (): ReadableCollection => $this->children->of($choice->childIds)
            : static fn (): ReadableCollection => new ArrayCollection();

        InvalidInput::all($chooseRoles, $chooseChildren);
        $roles = $chooseRoles();
        $children = $chooseChildren();

        $this->approvals->approve($id, $actorId, $roles, function (User $user) use ($children, $actorId): void {
            foreach ($children as $child) {
                $this->guardianship->linkParent($user->id(), $child, approvedBy: $actorId);
            }

            $this->registrations->forget($user->id());
        });

        return AccountSummary::of($this->approvals->account($id));
    }
}
