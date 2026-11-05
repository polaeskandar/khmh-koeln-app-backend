<?php

declare(strict_types=1);

namespace App\Library\Files\Model;

use RuntimeException;

final readonly class CsvReader
{
    /**
     * @return array<int, list<string>> the records by the line they start on, quoted line breaks included
     */
    public static function read(string $text, string $delimiter): array
    {
        $stream = fopen('php://temp', mode: 'r+');

        if ($stream === false) {
            throw new RuntimeException('The file could not be read.');
        }

        fwrite($stream, $text);
        rewind($stream);
        $records = [];
        $line = 1;

        while (($values = fgetcsv($stream, separator: $delimiter, escape: '')) !== false) {
            /** @var array<array-key, string|null> $values */
            $records[$line] = self::texts($values);
            $line = 1 + substr_count(substr($text, offset: 0, length: (int) ftell($stream)), needle: "\n");
        }

        fclose($stream);

        return $records;
    }

    /**
     * @param array<array-key, string|null> $values
     *
     * @return list<string>
     */
    private static function texts(array $values): array
    {
        return array_values(array_map(strval(...), $values));
    }
}
