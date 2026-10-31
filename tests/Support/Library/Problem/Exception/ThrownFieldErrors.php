<?php

declare(strict_types=1);

namespace App\Tests\Support\Library\Problem\Exception;

use App\Library\Problem\Exception\InvalidInput;
use PHPUnit\Framework\Assert;

final readonly class ThrownFieldErrors
{
    /**
     * @param callable(): mixed $action
     *
     * @return array<string, array{code: string, params?: array<string, string|int|float|bool|null>}>
     */
    public static function of(callable $action): array
    {
        try {
            $action();
        } catch (InvalidInput $invalid) {
            return $invalid->fieldErrors;
        }

        Assert::fail('No field errors were thrown.');
    }
}
