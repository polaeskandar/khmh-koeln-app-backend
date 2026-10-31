<?php

declare(strict_types=1);

namespace App\Library\Types\Model;

final readonly class Type
{
    private function __construct(
        private mixed $value,
    ) {
    }

    public static function of(mixed $value): self
    {
        return new self($value);
    }

    public function toScalarOrNull(): string|int|float|bool|null
    {
        return is_scalar($this->value) ? $this->value : null;
    }

    public function toStringOrNull(): ?string
    {
        return is_string($this->value) ? $this->value : null;
    }
}
