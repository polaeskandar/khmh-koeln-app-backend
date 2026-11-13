<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\RoleHolders;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\StaffAccounts;
use App\Context\Identity\Domain\ValueObject\StaffMember;
use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Context\Organization\Domain\Query\StaffAssignmentQueryInterface;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\StaffAccount;
use App\Workflow\Identity\Model\StaffAccountList;
use App\Workflow\Organization\Model\AssignmentView;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ListStaffAccountsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StaffAccounts $accounts,
        private Calendar $calendar,
        private StaffAssignmentQueryInterface $assignments,
        private RoleHolders $holders,
    ) {
    }

    public function __invoke(): StaffAccountList
    {
        $this->access->require(Action::Read, Resource::Users);

        $members = new ArrayCollection($this->accounts->listed($this->holders->staff(), $this->holders->all()));
        $ids = $members->map(static fn (StaffMember $member): Uuid => Uuid::fromString($member->id))->getValues();
        $assignments = $this->assignments->listOfAccounts($ids);
        $roles = $this->holders->listRolesOfAccounts($ids);

        return new StaffAccountList(
            $members->map(static fn (StaffMember $member): StaffAccount => StaffAccount::of(
                $member,
                $roles[$member->id] ?? [],
                $assignments
                    ->filter(static fn (StaffAssignment $assignment): bool => $assignment
                        ->accountId()
                        ->equals(Uuid::fromString($member->id)))
                    ->map(AssignmentView::of(...))
                    ->getValues(),
            ))->getValues(),
            $this->calendar->today()->toString(),
        );
    }
}
