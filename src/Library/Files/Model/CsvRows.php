<?php

declare(strict_types=1);

namespace App\Library\Files\Model;

final readonly class CsvRows
{
    /**
     * @param array<int, list<string>> $records
     *
     * @return array<int, array<string, string>> the rows by line number, each keyed by its lower-cased header
     */
    public static function of(array $records): array
    {
        $header = reset($records);
        $columns = array_map(strtolower(...), array_map(trim(...), $header === false ? [] : $header));
        $rows = [];

        foreach (array_slice($records, offset: 1, preserve_keys: true) as $line => $values) {
            if (trim(implode('', $values)) === '') {
                continue;
            }

            $values = array_pad(array_slice($values, offset: 0, length: \count($columns)), \count($columns), value: '');
            $rows[$line] = array_combine($columns, $values);
        }

        return $rows;
    }
}
