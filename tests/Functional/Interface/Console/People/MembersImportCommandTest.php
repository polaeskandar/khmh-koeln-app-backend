<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\People;

use App\Library\Types\Model\Integers;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MembersImportCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string HEADER = "first_name,last_name,display_name,service,stage\n";

    private string $file = '';

    #[Override]
    protected function setUp(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->file = (string) tempnam(sys_get_temp_dir(), prefix: 'members');
        $school = Structure::service();

        new Database(self::getContainer())->persisted(
            $school,
            Structure::stage($school, '2nd Class', position: 1),
            Structure::stage($school),
            Structure::service('Youth Meeting'),
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    public function testADryRunPrintsThePlanWritesNothingAndKeepsTheFile(): void
    {
        $this->write(self::HEADER . "Mina,Hanna,,Sunday School,3rd Class\nMina,Hanna,,Youth Meeting,\n");

        [$status, $output] = $this->import('app:members:import:dry-run');

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Would import 1 people with 2 memberships', $output);
        self::assertStringContainsString('line 2: Mina Hanna → Sunday School / 3rd Class', $output);
        self::assertStringContainsString('line 3: Mina Hanna → Youth Meeting', $output);
        self::assertSame([0, 0], $this->counts());
        self::assertFileExists($this->file);
    }

    public function testAnyInvalidLineStopsTheWholeImport(): void
    {
        $rows = array_fill(start_index: 0, count: 11, value: "Kiro,Nashed,,Sunday School,2nd Class\n");
        $rows[5] = "Marina,,,Sunday School,2nd Class\n";
        $rows[10] = "Bishoy,Adel,,Sunday School,Ninth Class\n";
        $this->write(self::HEADER . implode('', $rows));

        [$status, $output] = $this->import('app:members:import');

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('line 7: last_name: people.lastNameRequired', $output);
        self::assertStringContainsString('line 12: stage: meetings.chooseStage', $output);
        self::assertSame([0, 0], $this->counts());
        self::assertFileExists($this->file);
    }

    public function testAnImportRunsOnceDeletesTheFileAndSkipsNamesakes(): void
    {
        new Rosters(self::getContainer())->person('Kiro', 'Nashed');

        $csv =
            self::HEADER
            . "Mina,Hanna,Mimi,Sunday School,3rd Class\nmina,HANNA,,Sunday School,3rd Class\nKiro,Nashed,,Sunday School,\n";

        $this->write($csv);

        [$status, $output] = $this->import('app:members:import');

        self::assertSame(Command::SUCCESS, $status, $output);
        self::assertStringContainsString('line 3: mina HANNA is listed twice for Sunday School / 3rd Class', $output);
        self::assertStringContainsString('line 4: Kiro Nashed has a namesake, not added to Sunday School', $output);
        self::assertSame([2, 1], $this->counts());
        self::assertFileDoesNotExist($this->file);

        $this->write($csv);
        [, $again] = $this->import('app:members:import');
        self::assertStringContainsString('line 2: Mina Hanna is already on Sunday School / 3rd Class', $again);
        self::assertSame([2, 1], $this->counts());

        $this->write($csv);
        $this->import('app:members:import', ['--duplicates' => 'allow']);
        self::assertSame([3, 2], $this->counts());
    }

    public function testASemicolonFileWithABomImportsAndOtherEncodingsAreRejected(): void
    {
        $this->write(
            "\u{FEFF}first_name;last_name;display_name;service;stage\r\nJürgen;Müller;;Sunday School;2nd Class\r\n",
        );

        [$status] = $this->import('app:members:import');

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame([1, 1], $this->counts());

        $this->write("first_name;last_name;display_name;service;stage\nJ\xFCrgen;M\xFCller;;Sunday School;\n");
        [$rejected, $output] = $this->import('app:members:import');

        self::assertSame(Command::FAILURE, $rejected);
        self::assertStringContainsString('The file is not UTF-8.', $output);
        self::assertSame([], new Logs(self::getContainer())->containing('Müller'));
    }

    public function testAMisspelledColumnRefusesTheWholeFile(): void
    {
        $this->write("first_name,last_name,display_name,service,stages\nMina,Hanna,,Sunday School,3rd Class\n");

        [$status, $output] = $this->import('app:members:import');

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('The column "stages" is not one of', $output);
        self::assertSame([0, 0], $this->counts());
        self::assertFileExists($this->file);
    }

    private function write(string $content): void
    {
        file_put_contents($this->file, data: $content);
    }

    /**
     * @param array<string, string> $options
     *
     * @return array{int, string}
     */
    private function import(string $command, array $options = []): array
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find($command));
        $status = $tester->execute(['file' => $this->file, ...$options]);

        return [$status, $tester->getDisplay()];
    }

    /**
     * @return array{int, int}
     */
    private function counts(): array
    {
        $connection = new Database(self::getContainer())->connection();

        return [
            Integers::of($connection->fetchOne('SELECT count(*) FROM people.person')),
            Integers::of($connection->fetchOne('SELECT count(*) FROM people.membership')),
        ];
    }
}
