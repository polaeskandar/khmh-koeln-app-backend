<?php

declare(strict_types=1);

namespace App\Tools\CodingStandard;

use PHP_CodeSniffer\Util\Tokens;

final readonly class GapBelow
{
    public static function missingAfter(StatementLines $statement): ?int
    {
        $next = $statement->file->findNext(Tokens::$emptyTokens, $statement->end + 1, exclude: true);

        return is_int($next) && $statement->sitsRightAbove($next) ? $statement->end : null;
    }
}
