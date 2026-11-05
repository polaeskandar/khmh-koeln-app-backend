<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Privacy;

use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Interface\Console\Privacy\PrivacyFixture;
use App\Tests\Support\JsonBody;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class PrivacyExportCommandAccountTest extends KernelTestCase
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

    public function testAParentsExportHasTheAccountButNoSecrets(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [, $parent] = new PrivacyFixture(self::getContainer())->family();

        self::assertSame(Command::SUCCESS, $this->export(['--account' => $parent]));
        $data = $this->written();
        $account = JsonBody::asArray($data['account'] ?? null, 'account');

        self::assertSame(['kind', 'exportedAt', 'account', 'children', 'homeworkReports'], array_keys($data));

        self::assertSame(
            [
                'id',
                'email',
                'name',
                'language',
                'createdAt',
                'approvedAt',
                'disabledAt',
                'privacyNoticeAccepted',
                'roles',
                'registration',
                'memberAgeCheck',
            ],
            array_keys($account),
        );

        self::assertSame('hoda@example.org', $account['email'] ?? null);
        self::assertSame(['Parent'], $account['roles'] ?? null);

        self::assertSame(
            ['at' => '2026-10-30T10:00:00+01:00', 'version' => '2026-11'],
            $account['privacyNoticeAccepted'] ?? null,
        );

        self::assertSame([['name' => 'Mina Hanna', 'linkStatus' => 'approved']], $data['children'] ?? null);

        self::assertSame(
            'Mina',
            JsonBody::asArray(
                JsonBody::asArray($data['homeworkReports'] ?? null, 'reports')[0] ?? null,
                'report',
            )['child'] ?? null,
        );

        $content = (string) file_get_contents($this->file);
        self::assertStringNotContainsString('not-a-real-hash', $content);
        self::assertStringNotContainsString('password', $content);
        self::assertSame('0600', substr(sprintf('%o', fileperms($this->file)), -4));
    }

    public function testAParentWithoutLinksHasNoChildrenAndNobodyElse(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        new PrivacyFixture(self::getContainer())->family();
        $alone = new Families(self::getContainer())->account('alone@example.org', ['display_name' => 'Alone Parent']);

        $this->export(['--account' => $alone]);
        $data = $this->written();

        self::assertSame([], $data['children'] ?? null);
        self::assertSame([], $data['homeworkReports'] ?? null);
        self::assertStringNotContainsString('Mina', (string) file_get_contents($this->file));
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
