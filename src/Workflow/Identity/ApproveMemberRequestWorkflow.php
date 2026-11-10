<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountApprovals;
use App\Context\Identity\Application\Service\MemberAccounts;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\MemberRecord;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Context\People\Application\Service\MemberRecords;
use App\Context\People\Domain\Entity\Person;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;
use App\Workflow\Identity\Model\AccountSummary;
use App\Workflow\Identity\Model\MemberApproval;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

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
                Ids::toUuidOrNull($member->personId),
                fn (Person $person): User => $this->approved($id, $actorId, $person, $member),
            );
        } catch (InvalidInput $invalid) {
            throw $invalid->nestedIn(self::FIELDS);
        }

        $this->approvals->announce($approved);

        return AccountSummary::of($approved);
    }

    private function approved(string $id, string $actorId, Person $person, MemberApproval $member): User
    {
        $record = new MemberRecord($person->id(), $person->name()->fullName());

        return $this->approvals->approve(
            Ids::toUuid($id, notFound: 'user'),
            Uuid::fromString($actorId),
            new ArrayCollection(),
            function (User $user) use ($record, $member): void {
                $this->members->link($user, $record, $member->ageCheck, $member->guardianConsentNote);
                $this->registrations->forget($user->id());
            },
        );
    }
}
