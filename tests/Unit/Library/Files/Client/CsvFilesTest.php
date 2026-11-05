<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Files\Client;

use App\Library\Files\Client\CsvFiles;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\TestCase;

final class CsvFilesTest extends TestCase
{
    private string $file = '';

    #[Override]
    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), prefix: 'csv');
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_file($this->file)) {
            return;
        }

        unlink($this->file);
    }

    public function testASemicolonFileWithABomReadsByHeaderAndLineNumber(): void
    {
        file_put_contents(
            $this->file,
            data: "\u{FEFF}First_Name; Last_Name;stage\r\nMina;Hanna;3rd Class\r\n\r\n\"Kiro; Jr.\";Nashed\r\n",
        );

        self::assertSame(
            [
                2 => ['first_name' => 'Mina', 'last_name' => 'Hanna', 'stage' => '3rd Class'],
                4 => ['first_name' => 'Kiro; Jr.', 'last_name' => 'Nashed', 'stage' => ''],
            ],
            new CsvFiles()->rows($this->file),
        );
    }

    public function testACommaFileReadsToo(): void
    {
        file_put_contents($this->file, data: "first_name,last_name\nمينا,حنا\n");

        self::assertSame([2 => ['first_name' => 'مينا', 'last_name' => 'حنا']], new CsvFiles()->rows($this->file));
    }

    public function testAQuotedLineBreakStaysInItsFieldAndLinesCountOn(): void
    {
        file_put_contents($this->file, data: "first_name,display_name\nMina,\"Mimi\nHanna\"\nKiro,\n");

        self::assertSame(
            [
                2 => ['first_name' => 'Mina', 'display_name' => "Mimi\nHanna"],
                4 => ['first_name' => 'Kiro', 'display_name' => ''],
            ],
            new CsvFiles()->rows($this->file),
        );
    }

    public function testAFileThatIsNotUtf8IsRejected(): void
    {
        file_put_contents($this->file, data: "first_name;last_name\nJ\xFCrgen;M\xFCller\n");

        $this->expectException(InvalidArgumentException::class);

        new CsvFiles()->rows($this->file);
    }
}
