<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Console\Client;

use App\Library\Console\Client\HiddenPrompt;
use App\Library\Console\Client\SecretReader;
use App\Library\Console\Client\StandardInput;
use App\Library\Console\Exception\TerminalRequired;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SecretReaderTest extends TestCase
{
    public function testStandardInputGivesItsFirstLine(): void
    {
        $input = self::input("piped secret\nmore\n", interactive: false);

        self::assertSame('piped secret', self::reader()->fromStandardInput($input));
    }

    public function testTheTerminalAsksTwice(): void
    {
        $input = self::input("typed secret\ntyped secret\n", interactive: true);

        self::assertSame('typed secret', self::reader()->typed(self::io($input), $input, prompt: 'Secret'));
    }

    public function testTheTerminalIsRequiredToTypeASecret(): void
    {
        $input = self::input('', interactive: false);

        $this->expectException(TerminalRequired::class);

        self::reader()->typed(self::io($input), $input, prompt: 'Secret');
    }

    private static function input(string $content, bool $interactive): ArrayInput
    {
        $stream = fopen('php://memory', mode: 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);

        $input = new ArrayInput([]);
        $input->setStream($stream);
        $input->setInteractive($interactive);

        return $input;
    }

    private static function io(ArrayInput $input): SymfonyStyle
    {
        return new SymfonyStyle($input, new BufferedOutput());
    }

    private static function reader(): SecretReader
    {
        return new SecretReader(new HiddenPrompt(), new StandardInput());
    }
}
