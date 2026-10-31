<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

use InvalidArgumentException;
use Normalizer;

final readonly class InputText
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function of(?string $value): self
    {
        return new self($value ?? '');
    }

    public function trimmed(): self
    {
        return $this->replaced('/^\s+|\s+$/u', '');
    }

    public function collapsed(): self
    {
        return $this->replaced('/\s+/u', ' ')->trimmed();
    }

    public function folded(): self
    {
        $decomposed = Normalizer::normalize($this->collapsed()->value, Normalizer::FORM_D);

        return new self($decomposed === false ? $this->value : $decomposed)
            ->replaced('/\p{Mn}+/u', replacement: '')
            ->lowered();
    }

    public function lowered(): self
    {
        return new self(mb_strtolower($this->value));
    }

    public function length(): int
    {
        return mb_strlen($this->value);
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function toStringOrNull(): ?string
    {
        return $this->isEmpty() ? null : $this->value;
    }

    private function replaced(string $pattern, string $replacement): self
    {
        return new self(
            preg_replace($pattern, $replacement, $this->value) ?? throw new InvalidArgumentException(
                'The text is not valid UTF-8.',
            ),
        );
    }
}
