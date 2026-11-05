<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Platform\Privacy\Contract\AccountReferences;
use Symfony\Component\Uid\Uuid;

final readonly class AccountSweep
{
    /**
     * @param iterable<AccountReferences> $references
     */
    public static function of(iterable $references, Uuid $accountId): int
    {
        $kept = 0;

        foreach ($references as $context) {
            $kept += $context->forget($accountId);
        }

        return $kept;
    }
}
