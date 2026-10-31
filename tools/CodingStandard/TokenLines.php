<?php

declare(strict_types=1);

namespace App\Tools\CodingStandard;

use App\Library\Types\Model\Type;
use PHP_CodeSniffer\Files\File;

final readonly class TokenLines
{
    public function __construct(
        private File $file,
    ) {
    }

    public function line(int $position): int
    {
        return Type::of($this->file->getTokens()[$position]['line'] ?? null)->toIntOrNull() ?? 0;
    }

    public function code(int $position): string|int|float|bool|null
    {
        return Type::of($this->file->getTokens()[$position]['code'] ?? null)->toScalarOrNull();
    }

    public function isInsideParentheses(int $position): bool
    {
        return ($this->file->getTokens()[$position]['nested_parenthesis'] ?? null) !== null;
    }
}
