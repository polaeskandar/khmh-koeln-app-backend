<?php

declare(strict_types=1);

namespace App\Tools\CodingStandard\Sniffs\Commenting;

use App\Tools\CodingStandard\TokenLines;
use Override;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class NoCommentsSniff implements Sniff
{
    public const string COMMENT = 'Comment';

    public const string PROSE = 'DocblockProse';

    private const string TOOL_MARKER = '@mago-';

    /**
     * @return list<int|string>
     */
    #[Override]
    public function register(): array
    {
        return [T_COMMENT, T_DOC_COMMENT_OPEN_TAG];
    }

    /**
     * @param int $stackPtr
     */
    #[Override]
    public function process(File $phpcsFile, $stackPtr): void
    {
        if (new TokenLines($phpcsFile)->code($stackPtr) === T_DOC_COMMENT_OPEN_TAG) {
            self::rejectProse($phpcsFile, $stackPtr);

            return;
        }

        if (str_contains($phpcsFile->getTokensAsString($stackPtr, length: 1), self::TOOL_MARKER)) {
            return;
        }

        $phpcsFile->addError(
            'Name the code instead of commenting it; explain decisions in docs/',
            $stackPtr,
            self::COMMENT,
        );
    }

    private static function rejectProse(File $file, int $opener): void
    {
        $firstTagOrEnd = $file->findNext([T_DOC_COMMENT_TAG, T_DOC_COMMENT_CLOSE_TAG], $opener);
        $prose = $file->findNext(T_DOC_COMMENT_STRING, $opener, $firstTagOrEnd === false ? null : $firstTagOrEnd);

        if ($prose === false) {
            return;
        }

        $file->addError('A docblock holds only tags for what PHP cannot type; explain in docs/', $prose, self::PROSE);
    }
}
