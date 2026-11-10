<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Membership;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;
use App\Workflow\Organization\Model\AssignedGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ServantGroups
{
    /**
     * @param ReadableCollection<int, AssignedGroup> $groups the member's groups
     * @param ReadableCollection<int, StaffAssignment> $assignments those that cover them
     */
    private function __construct(
        private ReadableCollection $groups,
        private ReadableCollection $assignments,
    ) {
    }

    /**
     * @param ReadableCollection<int, Membership> $memberships the member's current ones
     * @param ReadableCollection<int, Service> $services the memberships' services
     * @param ReadableCollection<int, Stage> $stages the memberships' stages
     * @param ReadableCollection<int, StaffAssignment> $assignments those that cover the memberships
     */
    public static function of(
        ReadableCollection $memberships,
        ReadableCollection $services,
        ReadableCollection $stages,
        ReadableCollection $assignments,
    ): self {
        $ownRosters = $memberships->filter(
            static fn (Membership $membership): bool => $membership->stageId() === null,
        )->map(static fn (Membership $membership): string => $membership->serviceId()->toRfc4122());

        $rosters = $services->filter(static fn (Service $service): bool => $ownRosters->contains(
            $service->id()->toRfc4122(),
        ));

        $groups = new ArrayCollection([
            ...$rosters->map(AssignedGroup::ofService(...))->getValues(),
            ...$stages->map(AssignedGroup::ofStage(...))->getValues(),
        ]);

        return new self(new ArrayCollection(Collections::sortedBy($groups, keyOf: self::sortKeyOf(...))), $assignments);
    }

    /**
     * @return list<AssignedGroup> by service name, the service's own roster first, then by stage position
     */
    public function servedBy(User $servant): array
    {
        $held = $this->assignmentsOf($servant)->map(AssignedGroup::of(...))->map(self::groupOf(...));

        return GroupScope::assigned($held->getValues())
            ->filter($this->groups, self::groupOf(...))
            ->getValues();
    }

    public function isLedBy(User $servant): bool
    {
        return !$this
            ->assignmentsOf($servant)
            ->filter(static fn (StaffAssignment $assignment): bool => $assignment->stage() === null)
            ->isEmpty();
    }

    /**
     * @return ReadableCollection<int, StaffAssignment>
     */
    private function assignmentsOf(User $servant): ReadableCollection
    {
        return $this->assignments->filter(static fn (StaffAssignment $assignment): bool => $assignment
            ->accountId()
            ->equals($servant->id()));
    }

    private static function groupOf(AssignedGroup $group): Group
    {
        $stage = $group->stage;

        return new Group(Uuid::fromString($group->service->id), $stage === null ? null : Uuid::fromString($stage->id));
    }

    private static function sortKeyOf(AssignedGroup $group): string
    {
        $name = InputText::of($group->service->name)->lowered()->toString();

        return Text::format('%s|%07d', $name, ($group->stage->position ?? -1) + 1)->toString();
    }
}
