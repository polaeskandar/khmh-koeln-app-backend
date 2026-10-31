<?php

declare(strict_types=1);

namespace App\Library\Time\Exception;

use InvalidArgumentException;

final class InvalidLocalDate extends InvalidArgumentException
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('"%s" is not a date in the form YYYY-MM-DD.', $value));
    }
}
