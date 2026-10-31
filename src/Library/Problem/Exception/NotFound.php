<?php

declare(strict_types=1);

namespace App\Library\Problem\Exception;

use RuntimeException;

final class NotFound extends RuntimeException
{
    public static function of(string $thing): self
    {
        return new self('notFound.' . $thing);
    }
}
