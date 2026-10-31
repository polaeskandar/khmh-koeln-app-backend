<?php

declare(strict_types=1);

namespace App\Library\Problem\Exception;

use RuntimeException;

final class RuleViolation extends RuntimeException
{
    /**
     * @param array<string, string|int|float|bool|null> $params
     * @param array<string, object|list<object>> $extensions
     */
    public function __construct(
        string $code,
        public readonly array $params = [],
        public readonly array $extensions = [],
    ) {
        parent::__construct($code);
    }
}
