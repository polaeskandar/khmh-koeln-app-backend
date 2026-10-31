<?php

declare(strict_types=1);

namespace App\Library\Problem\Exception;

use RuntimeException;

final class InvalidInput extends RuntimeException
{
    /**
     * @param array<string, array{code: string, params?: array<string, string|int|float|bool|null>}> $fieldErrors
     */
    public function __construct(
        public readonly array $fieldErrors,
    ) {
        parent::__construct('checkFields');
    }
}
