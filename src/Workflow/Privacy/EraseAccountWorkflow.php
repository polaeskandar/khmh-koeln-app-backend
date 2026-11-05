<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\People\Application\Service\PersonErasures;
use App\Library\Problem\Exception\NotFound;
use App\Platform\Privacy\Contract\AccountReferences;
use App\Workflow\Privacy\Model\AccountSweep;
use App\Workflow\Privacy\Model\ErasureCounts;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Uid\Uuid;

final readonly class EraseAccountWorkflow
{
    /**
     * @param iterable<AccountReferences> $references
     */
    public function __construct(
        private AccountErasures $accounts,
        private PersonErasures $people,
        #[AutowireIterator(AccountReferences::TAG)]
        private iterable $references,
    ) {
    }

    public function __invoke(string $accountId): ErasureCounts
    {
        $id = Uuid::fromString($accountId);
        $sweep = fn (): int => AccountSweep::of($this->references, $id);

        if ($this->accounts->existing($id) === null) {
            $sweep();

            throw NotFound::of('user');
        }

        $guardianLinks = $this->people->linksOfAccount($id);
        $erased = $this->accounts->erase($id, $sweep);

        return ErasureCounts::of($erased, $guardianLinks, lateRows: $sweep());
    }
}
