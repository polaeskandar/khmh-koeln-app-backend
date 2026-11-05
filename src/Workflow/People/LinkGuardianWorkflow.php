<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\People\Application\Service\Children;
use App\Context\People\Application\Service\Guardianship;
use App\Platform\Doctrine\Contract\Transactions;

final readonly class LinkGuardianWorkflow
{
    public function __construct(
        private ParentAccounts $accounts,
        private Children $children,
        private Guardianship $guardianship,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(string $email, string $personId, string $relationship): void
    {
        $account = $this->accounts->activeByEmail($email);
        $child = $this->children->active($personId);

        $this->transactions->run(function () use ($account, $child, $relationship): void {
            $this->guardianship->link($account->id(), $child, $relationship);
            $this->accounts->giveParentRole($account);
        });
    }
}
