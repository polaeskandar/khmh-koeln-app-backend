<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Privacy;

use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Console\Privacy\PrivacyFixture;
use App\Tests\Support\JsonBody;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class PrivacyExportCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private string $file = '';

    #[Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/privacy-export-' . Uuid::v7()->toRfc4122() . '.json';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    public function testAChildsExportHasMembershipsACorrectedMarkAndAReportButNoEmail(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$mina] = new PrivacyFixture(self::getContainer())->family();

        self::assertSame(Command::SUCCESS, $this->export(['--person' => $mina->id()->toRfc4122()]));
        $data = $this->written();

        self::assertSame(
            ['kind', 'exportedAt', 'member', 'memberships', 'attendance', 'homeworkReports', 'parentAccounts'],
            array_keys($data),
        );

        self::assertSame(
            ['id', 'firstName', 'lastName', 'displayName', 'status', 'recordedSince'],
            array_keys(JsonBody::asArray($data['member'] ?? null, 'member')),
        );

        self::assertSame(
            [['service' => 'Sunday School', 'stage' => '3rd Class', 'from' => '2026-09-01', 'until' => null]],
            $data['memberships'] ?? null,
        );

        self::assertSame(
            [[
                'meeting' => 'Sunday class',
                'date' => '2026-11-08',
                'markedPresentAt' => '2026-11-08T12:00:00+01:00',
                'removedAt' => '2026-11-08T12:05:00+01:00',
                'removalNote' => 'Wrong child',
            ]],
            $data['attendance'] ?? null,
        );

        self::assertSame(
            'Learn the verse',
            JsonBody::asArray(
                JsonBody::asArray($data['homeworkReports'] ?? null, 'reports')[0] ?? null,
                'report',
            )['homework'] ?? null,
        );

        self::assertSame([['name' => 'Hoda Farag', 'linkStatus' => 'approved']], $data['parentAccounts'] ?? null);
        self::assertStringNotContainsString('@', (string) file_get_contents($this->file));
    }

    public function testAnExistingFileIsReplacedByOneOnlyTheOwnerCanRead(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$mina] = new PrivacyFixture(self::getContainer())->family();
        file_put_contents($this->file, data: 'old');
        chmod($this->file, permissions: 0o644);

        self::assertSame(Command::SUCCESS, $this->export(['--person' => $mina->id()->toRfc4122()]));

        self::assertSame('0600', substr(sprintf('%o', fileperms($this->file)), -4));
        self::assertSame('member', $this->written()['kind'] ?? null);
        self::assertSame([], glob(dirname($this->file) . '/.private-*'));
    }

    public function testTheRecordDateIsTheChurchDateOfItsCreation(): void
    {
        self::mockTime('2026-11-08T00:30:00+01:00');
        $person = new Rosters(self::getContainer())->person('Midnight');
        new Database(self::getContainer())->entityManager()->clear();

        self::assertSame(Command::SUCCESS, $this->export(['--person' => $person->id()->toRfc4122()]));

        self::assertSame(
            '2026-11-08',
            JsonBody::asArray($this->written()['member'] ?? null, 'member')['recordedSince'] ?? null,
        );
    }

    public function testWrongOptionsAndUnknownIdsWriteNothing(): void
    {
        self::assertSame(Command::FAILURE, $this->export(['--person' => Uuid::v7()->toRfc4122()]));
        self::assertSame(Command::INVALID, $this->export([]));

        self::assertSame(Command::INVALID, $this->export([
            '--person' => Uuid::v7()->toRfc4122(),
            '--account' => Uuid::v7()->toRfc4122(),
        ]));

        self::assertSame(Command::INVALID, $this->export(['--person' => 'nope']));
        self::assertFileDoesNotExist($this->file);
    }

    /**
     * @param array<string, string> $options
     */
    private function export(array $options): int
    {
        $command = new Application(self::bootKernel())->find('app:privacy:export');

        return new CommandTester($command)->execute([...$options, '--output' => $this->file]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function written(): array
    {
        return JsonBody::asArray(
            json_decode((string) file_get_contents($this->file), associative: true, flags: JSON_THROW_ON_ERROR),
            'export',
        );
    }
}
