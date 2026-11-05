<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

final readonly class ImportLine
{
    public function __construct(
        public int $line,
        public string $text,
    ) {
    }
}
