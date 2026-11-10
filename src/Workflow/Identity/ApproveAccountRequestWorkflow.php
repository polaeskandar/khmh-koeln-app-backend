<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountApprovals;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\AssignmentChanges;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Context\People\Application\Service\ChildChoices;
use App\Context\People\Application\Service\Guardianship;
use App\Context\People\Domain\Entity\Person;
use App\Library\Problem\Exception\InvalidInput;
use App\Platform\Access\Model\Group;
use App\Workflow\Identity\Model\AccountSummary;
use App\Workflow\Identity\Model\ApprovalChoice;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ApproveAccountRequestWorkflow
{
    private const string ASSIGNMENTS = 'assignments';

    public function __construct(
        private AccessCheck $access,
        private AccountApprovals $approvals,
        private ChildChoices $children,
        private Guardianship $guardianship,
        private AssignmentChanges $assignments,
    ) {
    }

    public function __invoke(string $id, string $actorId, ApprovalChoice $choice): AccountSummary
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'accounts.adminOnly');
        $this->access->require(Action::Update, Resource::Roles, refusal: 'accounts.adminOnly');
        $this->approvals->ensurePending($id);

        InvalidInput::all(
            fn (): ReadableCollection => $this->rolesOf($choice),
            fn (): ReadableCollection => $this->childrenOf($choice),
            fn (): ReadableCollection => $this->groupsOf($choice),
        );

        $children = $this->childrenOf($choice);
        $groups = $this->groupsOf($choice);
        $this->access->requireAssigningIn(...$groups->map(self::groupOf(...))->getValues());

        $approved = $this->approvals->approve($id, $actorId, $this->rolesOf($choice), fn (User $user) => $this->settle(
            $user,
            $children,
            $groups,
            $actorId,
        ));

        $this->approvals->announce($approved);

        return AccountSummary::of($approved);
    }

    /**
     * @return ReadableCollection<int, Role>
     */
    private function rolesOf(ApprovalChoice $choice): ReadableCollection
    {
        return $choice->isParent()
            ? $this->approvals->chosenParentRoles($choice->roleIds)
            : $this->approvals->chosenRoles($choice->roleIds);
    }

    /**
     * @return ReadableCollection<int, Person>
     */
    private function childrenOf(ApprovalChoice $choice): ReadableCollection
    {
        return $choice->isParent() ? $this->children->of($choice->childIds) : new ArrayCollection();
    }

    /**
     * @return ReadableCollection<int, ChosenPlacement>
     */
    private function groupsOf(ApprovalChoice $choice): ReadableCollection
    {
        return $choice->isParent()
            ? new ArrayCollection()
            : $this->assignments->chosen($choice->assignments, self::ASSIGNMENTS);
    }

    private static function groupOf(ChosenPlacement $group): Group
    {
        return new Group($group->service->id(), $group->stage?->id());
    }

    /**
     * @param ReadableCollection<int, Person> $children
     * @param ReadableCollection<int, ChosenPlacement> $groups
     */
    private function settle(User $user, ReadableCollection $children, ReadableCollection $groups, string $actorId): void
    {
        foreach ($children as $child) {
            $this->guardianship->linkParent($user->id(), $child, approvedBy: $actorId);
        }

        $this->assignments->settle($user->id(), $groups);
    }
}
