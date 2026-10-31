<?php

declare(strict_types=1);

namespace App\Library\Console\Client;

use App\Library\Console\Exception\ConfirmationMismatch;
use App\Library\Console\Exception\HiddenInputUnavailable;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class HiddenPrompt
{
    public function askConfirmed(SymfonyStyle $io, string $prompt, string $confirmationPrompt): string
    {
        $answer = $this->ask($io, $prompt);
        $confirmation = $this->ask($io, $confirmationPrompt);

        if ($answer === null || $answer !== $confirmation) {
            throw new ConfirmationMismatch();
        }

        return $answer;
    }

    private function ask(SymfonyStyle $io, string $prompt): ?string
    {
        $question = new Question($prompt)
            ->setHidden(hidden: true)
            ->setHiddenFallback(fallback: false)
            ->setTrimmable(trimmable: false);

        try {
            return self::withoutLineBreak($io->askQuestion($question));
        } catch (RuntimeException $exception) {
            throw new HiddenInputUnavailable($exception);
        }
    }

    private static function withoutLineBreak(mixed $answer): ?string
    {
        return is_string($answer) ? rtrim($answer, characters: "\r\n") : null;
    }
}
