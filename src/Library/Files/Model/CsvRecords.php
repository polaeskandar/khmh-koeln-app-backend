<?php

declare(strict_types=1);

namespace App\Library\Files\Model;

use InvalidArgumentException;

final readonly class CsvRecords
{
    private const string BOM = "\u{FEFF}";

    /**
     * @return array<int, list<string>> the records by the line they start on
     */
    public static function of(string $content): array
    {
        if (!mb_check_encoding($content, encoding: 'UTF-8')) {
            throw new InvalidArgumentException(
                'The file is not UTF-8. Save it as "CSV UTF-8" and run the import again.',
            );
        }

        $text = str_starts_with($content, self::BOM) ? substr($content, \strlen(self::BOM)) : $content;
        $header = explode(separator: "\n", string: $text, limit: 2)[0];

        return CsvReader::read($text, str_contains($header, ';') ? ';' : ',');
    }
}
