<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\CurrentAccounts;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\Access;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\AccountSummary;
use App\Workflow\Identity\Model\CurrentAccount;
use App\Workflow\Identity\Model\MemberSummary;
use App\Workflow\Identity\Model\PermissionGrant;
use App\Workflow\Identity\Model\RoleSummary;
use App\Workflow\MemberPortal\Model\MemberRoster;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;

final readonly class ShowCurrentAccountWorkflow
{
    public function __construct(
        private CurrentAccounts $accounts,
        private Calendar $calendar,
        private MemberRecords $records,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $userId): CurrentAccount
    {
        $user = $this->accounts->find($userId);

        return new CurrentAccount(
            account: AccountSummary::of($user),
            roles: self::rolesOf($user),
            permissions: new ArrayCollection($this->accounts->grantsOf($user))->map(self::grantOf(...))->getValues(),
            today: $this->calendar->today()->toString(),
            member: $this->memberOf($user),
        );
    }

    private function memberOf(User $user): ?MemberSummary
    {
        $personId = $user->member()->personId;

        if ($personId === null) {
            return null;
        }

        $record = $this->records->ownRecord($personId);

        return MemberSummary::of($personId, MemberRoster::of(
            $record,
            $this->structure->services(MemberRoster::serviceIdsOf($record)),
            $this->structure->stages(MemberRoster::stageIdsOf($record)),
        ));
    }

    private static function grantOf(Access $access): PermissionGrant
    {
        return new PermissionGrant($access->action->value, $access->resource->value);
    }

    /**
     * @return list<RoleSummary>
     */
    private static function rolesOf(User $user): array
    {
        return Collections::of($user->roles()->map(RoleSummary::of(...)))->matching(Criteria::create()->orderBy([
            'name' => Order::Ascending,
        ]))->getValues();
    }
}
