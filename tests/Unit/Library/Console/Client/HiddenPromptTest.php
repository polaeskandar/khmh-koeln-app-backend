<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Console\Client;

use App\Library\Console\Client\HiddenPrompt;
use App\Library\Console\Exception\ConfirmationMismatch;
use App\Library\Console\Exception\HiddenInputUnavailable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class HiddenPromptTest extends TestCase
{
    public function testTheConfirmedAnswerIsReturnedExactlyAsTyped(): void
    {
        $answer = new HiddenPrompt()->askConfirmed(self::io(' pass word ', ' pass word '), 'Password', 'Confirm');

        self::assertSame(' pass word ', $answer);
    }

    public function testADifferentConfirmationIsRefused(): void
    {
        $this->expectException(ConfirmationMismatch::class);

        new HiddenPrompt()->askConfirmed(self::io('secret-one', 'secret-two'), 'Password', 'Confirm');
    }

    public function testAnEmptyAnswerIsRefused(): void
    {
        $this->expectException(ConfirmationMismatch::class);

        new HiddenPrompt()->askConfirmed(self::io('', ''), 'Password', 'Confirm');
    }

    public function testAMissingConfirmationReportsTheInputAsUnavailable(): void
    {
        $this->expectException(HiddenInputUnavailable::class);

        new HiddenPrompt()->askConfirmed(self::io('secret-one'), 'Password', 'Confirm');
    }

    private static function io(string ...$answers): SymfonyStyle
    {
        $stream = fopen('php://memory', mode: 'r+');
        self::assertIsResource($stream);
        fwrite($stream, implode(PHP_EOL, $answers) . PHP_EOL);
        rewind($stream);

        $input = new ArrayInput([]);
        $input->setStream($stream);
        $input->setInteractive(interactive: true);

        return new SymfonyStyle($input, new BufferedOutput());
    }
}
