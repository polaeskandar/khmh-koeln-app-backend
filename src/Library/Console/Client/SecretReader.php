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

    public function read(
        SymfonyStyle $io,
        InputInterface $input,
        string $noneOption,
        string $standardInputOption,
        string $prompt,
    ): ?string {
        return match (true) {
            $input->getOption($noneOption) === true => null,
            $input->getOption($standardInputOption) === true => $this->standardInput->firstLine($input),
            default => $this->typed($io, $input, $prompt),
        };
    }

    private function typed(SymfonyStyle $io, InputInterface $input, string $prompt): string
    {
        if (!$input->isInteractive()) {
            throw new TerminalRequired();
        }

        return $this->prompt->askConfirmed($io, $prompt, confirmationPrompt: 'Repeat it');
    }
}
