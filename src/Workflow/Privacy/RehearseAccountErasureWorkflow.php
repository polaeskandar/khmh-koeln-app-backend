<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\People\Application\Service\PeopleExports;
use App\Platform\Privacy\Contract\AccountReferences;
use App\Workflow\Privacy\Model\AccountSweep;
use App\Workflow\Privacy\Model\ErasureCounts;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Uid\Uuid;

final readonly class RehearseAccountErasureWorkflow
{
    /**
     * @param iterable<AccountReferences> $references
     */
    public function __construct(
        private AccountErasures $accounts,
        private PeopleExports $people,
        #[AutowireIterator(AccountReferences::TAG)]
        private iterable $references,
    ) {
    }

    public function __invoke(string $accountId): ErasureCounts
    {
        $id = Uuid::fromString($accountId);
        $guardianLinks = $this->people->linksOfAccount($id)->count();
        $erased = $this->accounts->rehearse($id, fn (): int => AccountSweep::of($this->references, $id));

        return ErasureCounts::of($erased, $guardianLinks, lateRows: 0);
    }
}
