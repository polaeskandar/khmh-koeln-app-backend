<?php

declare(strict_types=1);

namespace App\Library\Strings\Model;

final readonly class SearchWords
{
    /**
     * @param list<string> $words
     */
    private function __construct(
        private array $words,
    ) {
    }

    public static function of(?string $query): self
    {
        $words = explode(' ', InputText::of($query)->folded()->toString());

        return new self(array_values(array_filter($words, static fn (string $word): bool => $word !== '')));
    }

    public function areAllIn(string $text): bool
    {
        $folded = InputText::of($text)->folded()->toString();

        return array_all($this->words, static fn (string $word): bool => str_contains($folded, $word));
    }
}
