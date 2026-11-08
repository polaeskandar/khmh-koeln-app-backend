<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\Enum\DuplicatePolicy;
use InvalidArgumentException;

final readonly class ImportPolicies
{
    private const string POLICIES = 'Pass --duplicates=skip or --duplicates=allow.';

    public static function of(string $duplicates): DuplicatePolicy
    {
        return DuplicatePolicy::tryFrom($duplicates) ?? throw new InvalidArgumentException(self::POLICIES);
    }
}
