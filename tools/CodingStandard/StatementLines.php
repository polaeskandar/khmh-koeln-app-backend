<?php

declare(strict_types=1);

namespace App\Tools\CodingStandard;

use PHP_CodeSniffer\Files\File;

final readonly class StatementLines
{
    private const array BODIES = [T_FUNCTION, T_CLOSURE];

    private const array BLOCK_OPENERS = [T_OPEN_CURLY_BRACKET, T_COLON];

    private function __construct(
        public File $file,
        private TokenLines $lines,
        public int $start,
        public int $end,
    ) {
    }

    public static function of(File $file, int $semicolon): self
    {
        return new self($file, new TokenLines($file), $file->findStartOfStatement($semicolon), $semicolon);
    }

    public function spansSeveralLinesOfABody(): bool
    {
        $inABody =
            $this->file->hasCondition($this->end, self::BODIES) && !$this->lines->isInsideParentheses($this->end);

        return $inABody && $this->lines->line($this->end) > $this->lines->line($this->start);
    }

    public function sitsRightBelow(int $previous): bool
    {
        $opensTheBlock = in_array($this->lines->code($previous), self::BLOCK_OPENERS, strict: true);
        $lineAbove = $this->lines->line($this->start) - 1;

        return !$opensTheBlock && $this->lines->line($previous) === $lineAbove;
    }

    public function sitsRightAbove(int $next): bool
    {
        $lineBelow = $this->lines->line($this->end) + 1;

        return $this->lines->code($next) !== T_CLOSE_CURLY_BRACKET && $this->lines->line($next) === $lineBelow;
    }
}
