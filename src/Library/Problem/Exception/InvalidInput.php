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

    /**
     * @template First of object
     * @template Second of object
     *
     * @param callable(): First $first
     * @param callable(): Second $second
     *
     * @return array{First, Second}
     */
    public static function both(callable $first, callable $second): array
    {
        try {
            $one = $first();
        } catch (InvalidInput $invalid) {
            throw $invalid->withErrorsOf($second);
        }

        return [$one, $second()];
    }

    /**
     * @param callable(): object $check
     */
    private function withErrorsOf(callable $check): self
    {
        try {
            $check();
        } catch (InvalidInput $invalid) {
            return new self([...$this->fieldErrors, ...$invalid->fieldErrors]);
        }

        return $this;
    }
}
