<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\People\Application\Service\Children;
use App\Context\People\Application\Service\Guardianship;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Model\Ids;

final readonly class RevokeGuardianWorkflow
{
    public function __construct(
        private ParentAccounts $accounts,
        private Children $children,
        private Guardianship $guardianship,
    ) {
    }

    public function __invoke(string $email, string $personId): void
    {
        $account = $this->accounts->byEmail($email);
        $child = $this->children->find(Ids::toUuid($personId, notFound: 'person')) ?? throw NotFound::of('person');

        $this->guardianship->revoke($account->id(), $child);
    }
}
