<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Platform\Privacy\Contract\AccountReferencesInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Uid\Uuid;

final readonly class AccountSweeps
{
    /**
     * @param iterable<AccountReferencesInterface> $references
     */
    public function __construct(
        #[AutowireIterator(AccountReferencesInterface::TAG)]
        private iterable $references,
    ) {
    }

    public function sweep(Uuid $accountId): int
    {
        $kept = 0;

        foreach ($this->references as $context) {
            $kept += $context->forget($accountId);
        }

        return $kept;
    }
}
