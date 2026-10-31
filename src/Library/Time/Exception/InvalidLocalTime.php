<?php

declare(strict_types=1);

namespace App\Library\Time\Exception;

use InvalidArgumentException;

final class InvalidLocalTime extends InvalidArgumentException
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('"%s" is not a time in the form HH:MM.', $value));
    }
}
