<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\People\Application\Service\Guardianship;
use App\Context\People\Domain\Enum\GuardianKind;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;
use App\Platform\Doctrine\Contract\Transactions;

final readonly class LinkGuardianWorkflow
{
    public function __construct(
        private ParentAccounts $accounts,
        private Guardianship $guardianship,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(string $email, string $personId, string $relationship): void
    {
        $account = $this->accounts->activeByEmail($email);
        $child = $this->guardianship->getActiveChild(Ids::toUuid($personId, notFound: 'person'));

        $kind = GuardianKind::tryFrom($relationship) ?? throw InvalidInput::field(
            'relationship',
            'validation.invalidChoice',
        );

        $this->transactions->run(function () use ($account, $child, $kind): void {
            $this->guardianship->linkGuardian($account->id(), $child, $kind);
            $this->accounts->giveParentRole($account);
        });
    }
}
