<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\People\Application\Service\Guardianship;
use App\Workflow\People\Model\LinkedChild;

final readonly class ListGuardianLinksByEmailWorkflow
{
    public function __construct(
        private ParentAccounts $accounts,
        private Guardianship $guardianship,
    ) {
    }

    /**
     * @return list<LinkedChild>
     */
    public function __invoke(string $email): array
    {
        $account = $this->accounts->getByEmail($email);

        return $this->guardianship
            ->approvedOf($account->id())
            ->map(LinkedChild::of(...))
            ->getValues();
    }
}
