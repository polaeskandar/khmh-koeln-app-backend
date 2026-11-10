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
use App\Library\Problem\Model\Ids;
use App\Workflow\Identity\Model\AccountSummary;
use App\Workflow\Identity\Model\ApprovalChoice;
use App\Workflow\Organization\Model\StructureGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

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
        $accountId = Ids::toUuid($id, notFound: 'user');
        $this->approvals->ensurePending($accountId);

        InvalidInput::all(
            fn (): ReadableCollection => $this->rolesOf($choice),
            fn (): ReadableCollection => $this->childrenOf($choice),
            fn (): ReadableCollection => $this->groupsOf($choice),
        );

        $approved = $this->approved($accountId, Uuid::fromString($actorId), $choice);
        $this->approvals->announce($approved);

        return AccountSummary::of($approved);
    }

    private function approved(Uuid $accountId, Uuid $actor, ApprovalChoice $choice): User
    {
        $children = $this->childrenOf($choice);
        $groups = $this->groupsOf($choice);
        $this->access->requireAssigningIn(...$groups->map(StructureGroup::ofPlacement(...))->getValues());

        return $this->approvals->approve($accountId, $actor, $this->rolesOf($choice), fn (User $user) => $this->settle(
            $user,
            $children,
            $groups,
            $actor,
        ));
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
        return $choice->isParent() ? $this->children->chosen($choice->childIds) : new ArrayCollection();
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

    /**
     * @param ReadableCollection<int, Person> $children
     * @param ReadableCollection<int, ChosenPlacement> $groups
     */
    private function settle(User $user, ReadableCollection $children, ReadableCollection $groups, Uuid $actor): void
    {
        foreach ($children as $child) {
            $this->guardianship->linkParent($user->id(), $child, approvedBy: $actor);
        }

        $this->assignments->settle($user->id(), $groups);
    }
}
