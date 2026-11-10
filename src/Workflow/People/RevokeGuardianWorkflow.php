<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\People\Application\Service\Guardianship;
use App\Context\People\Domain\Query\PersonQueryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Model\Ids;

final readonly class RevokeGuardianWorkflow
{
    public function __construct(
        private ParentAccounts $accounts,
        private PersonQueryInterface $people,
        private Guardianship $guardianship,
    ) {
    }

    public function __invoke(string $email, string $personId): void
    {
        $account = $this->accounts->byEmail($email);
        $child = $this->people->find(Ids::toUuid($personId, notFound: 'person')) ?? throw NotFound::of('person');

        $this->guardianship->revoke($account->id(), $child);
    }
}
