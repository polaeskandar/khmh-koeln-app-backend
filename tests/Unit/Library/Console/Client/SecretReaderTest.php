<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Console\Client;

use App\Library\Console\Client\HiddenPrompt;
use App\Library\Console\Client\SecretReader;
use App\Library\Console\Client\StandardInput;
use App\Library\Console\Exception\TerminalRequired;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SecretReaderTest extends TestCase
{
    public function testTheNoneOptionWinsOverStandardInput(): void
    {
        self::assertNull(self::read(['--none' => true, '--stdin' => true], "piped\n", interactive: false));
    }

    public function testStandardInputGivesItsFirstLine(): void
    {
        self::assertSame('piped secret', self::read(['--stdin' => true], "piped secret\nmore\n", interactive: false));
    }

    public function testTheTerminalAsksTwice(): void
    {
        self::assertSame('typed secret', self::read([], "typed secret\ntyped secret\n", interactive: true));
    }

    public function testTheTerminalIsRequiredToTypeASecret(): void
    {
        $this->expectException(TerminalRequired::class);

        self::read([], '', interactive: false);
    }

    /**
     * @param array<string, bool> $options
     */
    private static function read(array $options, string $content, bool $interactive): ?string
    {
        $stream = fopen('php://memory', mode: 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);

        $input = new ArrayInput($options, new InputDefinition([
            new InputOption('none', mode: InputOption::VALUE_NONE),
            new InputOption('stdin', mode: InputOption::VALUE_NONE),
        ]));

        $input->setStream($stream);
        $input->setInteractive($interactive);

        return new SecretReader(new HiddenPrompt(), new StandardInput())->read(
            new SymfonyStyle($input, new BufferedOutput()),
            $input,
            noneOption: 'none',
            standardInputOption: 'stdin',
            prompt: 'Secret',
        );
    }
}
