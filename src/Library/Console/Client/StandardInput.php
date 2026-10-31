<?php

declare(strict_types=1);

namespace App\Library\Console\Client;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;

final readonly class StandardInput
{
    public function firstLine(InputInterface $input): string
    {
        $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $line = fgets($stream ?? STDIN);

        return $line === false ? '' : rtrim($line, characters: "\r\n");
    }
}
