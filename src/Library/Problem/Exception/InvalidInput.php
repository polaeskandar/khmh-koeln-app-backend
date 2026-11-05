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
     * @param callable(): (object|string|null) ...$checks
     */
    public static function all(callable ...$checks): void
    {
        $fieldErrors = array_merge(...array_map(self::errorsOf(...), array_values($checks)));

        if ($fieldErrors !== []) {
            throw new self($fieldErrors);
        }
    }

    public function nestedIn(string $parent): self
    {
        $fields = array_map(
            static fn (string $field): string => $parent . '.' . $field,
            array_keys($this->fieldErrors),
        );

        return new self(array_combine($fields, array_values($this->fieldErrors)));
    }

    /**
     * @param callable(): (object|string|null) $check
     *
     * @return array<string, array{code: string, params?: array<string, string|int|float|bool|null>}>
     */
    private static function errorsOf(callable $check): array
    {
        try {
            $check();
        } catch (InvalidInput $invalid) {
            return $invalid->fieldErrors;
        }

        return [];
    }
}
