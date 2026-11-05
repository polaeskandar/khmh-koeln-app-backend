<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\MemberAccounts;
use App\Context\Identity\Domain\ValueObject\MemberRecord;
use App\Context\People\Application\Service\MemberRecords;
use App\Context\People\Domain\Entity\Person;

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

        $this->records->whileLinkable($personId, function (Person $person) use ($account, $ageCheck, $note): void {
            $record = new MemberRecord($person->id(), $person->name()->full());
            $this->accounts->link($account, $record, $ageCheck, $note);
        });
    }
}
