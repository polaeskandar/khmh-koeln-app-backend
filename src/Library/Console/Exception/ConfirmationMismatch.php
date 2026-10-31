<?php

declare(strict_types=1);

namespace App\Library\Console\Exception;

use RuntimeException;

final class ConfirmationMismatch extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The answer was empty or its confirmation did not match.');
    }
}
