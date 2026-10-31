<?php

declare(strict_types=1);

namespace App\Tools\CodingStandard;

use PHP_CodeSniffer\Util\Tokens;

final readonly class GapAbove
{
    public static function missingAfter(StatementLines $statement): ?int
    {
        $previous = $statement->file->findPrevious(Tokens::$emptyTokens, $statement->start - 1, exclude: true);

        return is_int($previous) && $statement->sitsRightBelow($previous) ? $previous : null;
    }
}
