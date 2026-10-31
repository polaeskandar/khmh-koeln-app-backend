<?php

declare(strict_types=1);

namespace App\Tools\CodingStandard\Sniffs\Spacing;

use App\Tools\CodingStandard\GapAbove;
use App\Tools\CodingStandard\GapBelow;
use App\Tools\CodingStandard\StatementLines;
use Override;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class MultiLineStatementSpacingSniff implements Sniff
{
    public const string BEFORE = 'NoBlankLineBefore';

    public const string AFTER = 'NoBlankLineAfter';

    /**
     * @return list<int|string>
     */
    #[Override]
    public function register(): array
    {
        return [T_SEMICOLON];
    }

    /**
     * @param int $stackPtr
     */
    #[Override]
    public function process(File $phpcsFile, $stackPtr): void
    {
        $statement = StatementLines::of($phpcsFile, $stackPtr);

        if (!$statement->spansSeveralLinesOfABody()) {
            return;
        }

        self::report($phpcsFile, GapAbove::missingAfter($statement), $statement->start, self::BEFORE);
        self::report($phpcsFile, GapBelow::missingAfter($statement), $statement->end, self::AFTER);
    }

    private static function report(File $file, ?int $missingAfter, int $statementPosition, string $code): void
    {
        $message = 'Expected 1 blank line around a statement that spans several lines';

        if ($missingAfter === null || !$file->addFixableError($message, $statementPosition, $code)) {
            return;
        }

        $file->fixer->addNewline($missingAfter);
    }
}
