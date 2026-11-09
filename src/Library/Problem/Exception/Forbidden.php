<?php

declare(strict_types=1);

namespace App\Library\Problem\Exception;

use RuntimeException;

final class Forbidden extends RuntimeException
{
    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    public function __construct(
        string $code,
        public readonly array $params = [],
    ) {
        parent::__construct($code);
    }
}
