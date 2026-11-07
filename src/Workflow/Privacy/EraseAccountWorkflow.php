<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\People\Application\Service\PeopleExports;
use App\Library\Problem\Exception\NotFound;
use App\Platform\Privacy\Contract\AccountReferences;
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
        private PeopleExports $people,
        #[AutowireIterator(AccountReferences::TAG)]
        private iterable $references,
    ) {
    }

    public function __invoke(string $accountId): ErasureCounts
    {
        $id = Uuid::isValid($accountId) ? Uuid::fromString($accountId) : throw NotFound::of('account');

        if (!$this->accounts->exists($id)) {
            $this->swept($id);

            throw NotFound::of('account');
        }

        $guardianLinks = $this->people->linksOfAccount($id)->count();
        $erased = $this->accounts->erase($id, fn (): int => $this->swept($id));

        return ErasureCounts::of($erased, $guardianLinks, lateRows: $this->swept($id));
    }

    private function swept(Uuid $accountId): int
    {
        $kept = 0;

        foreach ($this->references as $context) {
            $kept += $context->forget($accountId);
        }

        return $kept;
    }
}
