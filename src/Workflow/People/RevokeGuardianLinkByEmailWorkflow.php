<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\People\Application\Service\Children;
use App\Context\People\Application\Service\Guardianship;
use App\Library\Problem\Exception\NotFound;

final readonly class RevokeGuardianLinkByEmailWorkflow
{
    public function __construct(
        private ParentAccounts $accounts,
        private Children $children,
        private Guardianship $guardianship,
    ) {
    }

    public function __invoke(string $email, string $personId): void
    {
        $account = $this->accounts->getByEmail($email);
        $child = $this->children->find($personId) ?? throw NotFound::of('person');

        $this->guardianship->revoke($account->id(), $child);
    }
}
