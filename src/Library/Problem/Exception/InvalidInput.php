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

    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    public static function field(string $field, string $code, array $params = []): self
    {
        return new self([$field => $params === [] ? ['code' => $code] : ['code' => $code, 'params' => $params]]);
    }

    public static function tooLong(string $field, int $max): self
    {
        return self::field($field, 'validation.tooLong', ['max' => $max]);
    }
}
