<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Console\Client;

use App\Library\Console\Client\StandardInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

final class StandardInputTest extends TestCase
{
    public function testTheFirstLineIsReadWithoutItsLineBreak(): void
    {
        self::assertSame(' secret one ', new StandardInput()->firstLine(self::input(" secret one \r\nsecond\n")));
    }

    public function testAnEmptyStreamGivesAnEmptyLine(): void
    {
        self::assertSame('', new StandardInput()->firstLine(self::input('')));
    }

    private static function input(string $content): ArrayInput
    {
        $stream = fopen('php://memory', mode: 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);
        $input = new ArrayInput([]);
        $input->setStream($stream);

        return $input;
    }
}
