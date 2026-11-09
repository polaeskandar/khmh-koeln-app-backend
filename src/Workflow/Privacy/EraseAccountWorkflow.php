<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\People\Application\Service\PersonErasures;
use App\Library\Problem\Exception\NotFound;
use App\Workflow\Privacy\Model\ErasureCounts;
use Symfony\Component\Uid\Uuid;

final readonly class EraseAccountWorkflow
{
    public function __construct(
        private AccountErasures $accounts,
        private PersonErasures $people,
    ) {
    }

    public function __invoke(string $accountId): ErasureCounts
    {
        $id = Uuid::isValid($accountId) ? Uuid::fromString($accountId) : throw NotFound::of('account');
        $guardianLinks = $this->people->countGuardianLinksOf($id);

        return ErasureCounts::of($this->accounts->erase($id), $guardianLinks);
    }
}
