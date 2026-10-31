<?php

declare(strict_types=1);

namespace App\Library\Console\Exception;

use InvalidArgumentException;

final class InvalidOption extends InvalidArgumentException
{
    public function __construct(string $option, string $choices)
    {
        parent::__construct(sprintf('The option --%s takes one of: %s.', $option, $choices));
    }
}
