<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

use Override;
use Stringable;

final readonly class Text implements Stringable
{
    private function __construct(
        private string $value,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    public static function format(string $pattern, string|int|float ...$values): self
    {
        return new self(vsprintf($pattern, $values));
    }

    public function lower(): self
    {
        return new self(mb_strtolower($this->value));
    }

    public function startsWith(string $prefix): bool
    {
        return str_starts_with($this->value, $prefix);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
