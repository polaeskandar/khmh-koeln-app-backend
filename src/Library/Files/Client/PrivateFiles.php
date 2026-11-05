<?php

declare(strict_types=1);

namespace App\Library\Files\Client;

use RuntimeException;

final readonly class PrivateFiles
{
    private const int OWNER_ONLY = 0o600;

    private const string TEMPORARY_PREFIX = '.private-';

    public function write(string $path, string $content): void
    {
        $temporary = self::privateFileBeside($path);

        try {
            self::put($temporary, $content);
            self::replace($temporary, $path);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private static function privateFileBeside(string $path): string
    {
        $temporary = tempnam(dirname($path), self::TEMPORARY_PREFIX);

        if ($temporary === false) {
            throw new RuntimeException('No private file could be created.');
        }

        chmod($temporary, self::OWNER_ONLY);

        return $temporary;
    }

    private static function put(string $file, string $content): void
    {
        if (file_put_contents($file, $content, LOCK_EX) === false) {
            throw new RuntimeException('The file could not be written.');
        }
    }

    private static function replace(string $from, string $to): void
    {
        if (!rename($from, $to)) {
            throw new RuntimeException('The file could not be moved into place.');
        }
    }
}
