<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\MemberAccounts;
use App\Context\Identity\Domain\Enum\AgeCheck;
use App\Context\Identity\Domain\ValueObject\MemberRecord;
use App\Context\People\Application\Service\MemberRecords;
use App\Context\People\Domain\Entity\Person;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;

final readonly class LinkMemberWorkflow
{
    public function __construct(
        private MemberAccounts $accounts,
        private MemberRecords $records,
    ) {
    }

    public function __invoke(string $email, string $personId, string $ageCheck, ?string $note): void
    {
        $account = $this->accounts->linkable($email);
        $age = AgeCheck::tryFrom($ageCheck) ?? throw InvalidInput::field('ageCheck', 'accounts.chooseAge');

        $this->records->whileLinkable(Ids::toUuidOrNull($personId), function (Person $person) use (
            $account,
            $age,
            $note,
        ): void {
            $record = new MemberRecord($person->id(), $person->name()->fullName());
            $this->accounts->link($account, $record, $age, $note);
        });
    }
}
