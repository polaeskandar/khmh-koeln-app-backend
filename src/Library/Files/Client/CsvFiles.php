<?php

declare(strict_types=1);

namespace App\Library\Files\Client;

use App\Library\Files\Model\CsvRecords;
use App\Library\Files\Model\CsvRows;
use RuntimeException;

final readonly class CsvFiles
{
    /**
     * @return array<int, array<string, string>> the rows by line number, each keyed by its lower-cased header
     */
    public function rows(string $path): array
    {
        $content = is_readable($path) ? file_get_contents($path) : false;

        if ($content === false) {
            throw new RuntimeException('The file could not be read.');
        }

        return CsvRows::of(CsvRecords::of($content));
    }

    public function delete(string $path): void
    {
        if (!unlink($path)) {
            throw new RuntimeException('The file could not be deleted.');
        }
    }
}
