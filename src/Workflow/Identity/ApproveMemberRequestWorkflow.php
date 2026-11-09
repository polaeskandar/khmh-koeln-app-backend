<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountApprovals;
use App\Context\Identity\Application\Service\MemberAccounts;
use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\MemberRecord;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Context\People\Application\Service\MemberRecords;
use App\Context\People\Domain\Entity\Person;
use App\Library\Problem\Exception\InvalidInput;
use App\Workflow\Identity\Model\AccountSummary;
use App\Workflow\Identity\Model\MemberApproval;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ApproveMemberRequestWorkflow
{
    private const string FIELDS = 'member';

    public function __construct(
        private AccessCheck $access,
        private AccountApprovals $approvals,
        private MemberAccounts $members,
        private MemberRecords $records,
        private RegistrationServices $registrations,
    ) {
    }

    public function __invoke(string $id, string $actorId, MemberApproval $member): AccountSummary
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'accounts.adminOnly');

        try {
            $approved = $this->records->whileLinkable(
                $member->personId,
                fn (Person $person): Account => $this->approved($id, $actorId, $person, $member),
            );
        } catch (InvalidInput $invalid) {
            throw $invalid->nestedIn(self::FIELDS);
        }

        $this->approvals->announce($approved);

        return AccountSummary::of($approved);
    }

    private function approved(string $id, string $actorId, Person $person, MemberApproval $member): Account
    {
        $record = new MemberRecord($person->id(), $person->name()->fullName());

        return $this->approvals->approve($id, $actorId, new ArrayCollection(), function (Account $account) use (
            $record,
            $member,
        ): void {
            $this->members->link($account, $record, $member->ageCheck, $member->guardianConsentNote);
            $this->registrations->forget($account->id());
        });
    }
}
