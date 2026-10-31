<?php

declare(strict_types=1);

namespace App\Library\Console\Exception;

use RuntimeException;
use Throwable;

final class HiddenInputUnavailable extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(
            sprintf('A hidden answer could not be read (%s).', rtrim($previous->getMessage(), characters: '.')),
            previous: $previous,
        );
    }
}
