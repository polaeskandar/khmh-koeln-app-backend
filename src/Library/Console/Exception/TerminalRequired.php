<?php

declare(strict_types=1);

namespace App\Library\Console\Exception;

use RuntimeException;

final class TerminalRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('There is no terminal to type the answer in; pass it on standard input instead.');
    }
}
