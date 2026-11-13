<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Access\Application\Service\RoleHolders;
use App\Context\Access\Domain\Entity\Role;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\CurrentAccounts;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Query\StaffAssignmentQueryInterface;
use App\Context\People\Application\Service\MemberRecords;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use App\Workflow\Access\Model\RoleSummary;
use App\Workflow\Identity\Model\AccountAccess;
use App\Workflow\Identity\Model\AccountPermission;
use App\Workflow\Identity\Model\AccountSummary;
use App\Workflow\Identity\Model\CurrentAccount;
use App\Workflow\Identity\Model\MemberSummary;
use App\Workflow\Organization\Model\AssignedGroup;
use App\Workflow\People\Model\MemberRoster;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Symfony\Component\Uid\Uuid;

final readonly class ShowCurrentAccountWorkflow
{
    public function __construct(
        private CurrentAccounts $accounts,
        private Calendar $calendar,
        private MemberRecords $records,
        private StructureLookup $structure,
        private StaffAssignmentQueryInterface $assignments,
        private AccountLookup $lookup,
        private RoleHolders $holders,
    ) {
    }

    public function __invoke(string $userId): CurrentAccount
    {
        $user = $this->accounts->get(Uuid::fromString($userId));

        return new CurrentAccount(
            account: AccountSummary::of($user, $this->lookup->nameOf($user)),
            access: new AccountAccess(
                roles: self::summariesOf($this->holders->listRolesOf($user->id())),
                permissions: new ArrayCollection($this->holders->grantsOf($user->id()))->map(
                    AccountPermission::of(...),
                )->getValues(),
                assignments: $this->assignments
                    ->listOfAccounts([$user->id()])
                    ->map(AssignedGroup::of(...))
                    ->getValues(),
            ),
            today: $this->calendar->today()->toString(),
            member: $this->memberOf($user),
        );
    }

    private function memberOf(User $user): ?MemberSummary
    {
        $personId = $user->person()->isMember() ? $user->personId() : null;

        if ($personId === null) {
            return null;
        }

        $record = $this->records->getOwnRecord($personId);

        return MemberSummary::of($personId, MemberRoster::of(
            $record,
            $this->structure->listServices(MemberRoster::serviceIdsOf($record)),
            $this->structure->listStages(MemberRoster::stageIdsOf($record)),
        ));
    }

    /**
     * @param list<Role> $roles
     *
     * @return list<RoleSummary>
     */
    private static function summariesOf(array $roles): array
    {
        return Collections::of(new ArrayCollection($roles)->map(
            RoleSummary::of(...),
        ))->matching(Criteria::create()->orderBy([
            'name' => Order::Ascending,
        ]))->getValues();
    }
}
