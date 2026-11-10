<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

use Override;
use Stringable;
use Symfony\Component\String\ByteString;

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

    public function replaceMatches(string $pattern, string $replacement): self
    {
        return new self(
            new ByteString($this->value)
                ->replaceMatches($pattern, $replacement)
                ->toString(),
        );
    }

    public function matches(string $pattern): bool
    {
        return preg_match($pattern, $this->value) === 1;
    }

    public function isUtf8(): bool
    {
        return new ByteString($this->value)->isUtf8();
    }

    public function isDigits(): bool
    {
        return ctype_digit($this->value);
    }

    public function startsWith(string $prefix): bool
    {
        return str_starts_with($this->value, $prefix);
    }

    /**
     * @param non-empty-string $separator
     *
     * @return list<string>
     */
    public function split(string $separator): array
    {
        return explode($separator, $this->value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
