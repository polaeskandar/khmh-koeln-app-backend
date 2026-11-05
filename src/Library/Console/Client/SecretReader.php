<?php

declare(strict_types=1);

namespace App\Library\Console\Client;

use App\Library\Console\Exception\TerminalRequired;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class SecretReader
{
    public function __construct(
        private HiddenPrompt $prompt,
        private StandardInput $standardInput,
    ) {
    }

    public function fromStandardInput(InputInterface $input): string
    {
        return $this->standardInput->firstLine($input);
    }

    public function typed(SymfonyStyle $io, InputInterface $input, string $prompt): string
    {
        if (!$input->isInteractive()) {
            throw new TerminalRequired();
        }

        return $this->prompt->askConfirmed($io, $prompt, confirmationPrompt: 'Repeat it');
    }
}
