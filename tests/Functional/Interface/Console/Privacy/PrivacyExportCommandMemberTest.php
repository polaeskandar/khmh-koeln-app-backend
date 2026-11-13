<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Privacy;

use App\Library\Types\Model\Type;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Learning\Domain\Entity\CopticCourse;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class PrivacyExportCommandMemberTest extends KernelTestCase
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

    public function testAMembersCopyHasTheAgeCheckTheRecordAndTheirOwnReport(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $nevine = $course->member('nevine@example.org', 'Nevine');
        $course->member('hoda@example.org', 'Hoda');
        $course->report('greeting', $nevine, 'nevine@example.org');

        self::assertSame(Command::SUCCESS, $this->export($this->accountOf('nevine@example.org')));
        $data = $this->written();
        $account = JsonBody::asArray($data['account'] ?? null, 'account');
        $record = JsonBody::asArray($data['memberRecord'] ?? null, 'memberRecord');

        self::assertSame(
            ['kind', 'exportedAt', 'account', 'children', 'homeworkReports', 'memberRecord'],
            array_keys($data),
        );

        self::assertSame(['result' => 'sixteenOrOlder', 'parentAgreed' => null], $account['memberAgeCheck'] ?? null);
        self::assertSame('member', $record['kind'] ?? null);
        self::assertCount(1, JsonBody::asArray($record['memberships'] ?? null, 'memberships'));
        self::assertCount(1, JsonBody::asArray($record['homeworkReports'] ?? null, 'homeworkReports'));
        self::assertStringNotContainsString('hoda@example.org', (string) file_get_contents($this->file));
    }

    public function testAYouthsCopyHasTheParentsAgreement(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $mina = $course->member('mina@example.org', 'Mina');

        new MemberLinks(self::getContainer())->link(
            $this->accountOf('mina@example.org'),
            $mina->id()->toRfc4122(),
            'guardianConsent',
            'Father agreed after the liturgy.',
        );

        self::assertSame(Command::SUCCESS, $this->export($this->accountOf('mina@example.org')));

        self::assertSame(
            ['result' => 'guardianConsent', 'parentAgreed' => 'Father agreed after the liturgy.'],
            JsonBody::asArray($this->written()['account'] ?? null, 'account')['memberAgeCheck'] ?? null,
        );
    }

    private function accountOf(string $email): string
    {
        return Type::of(new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT id::text FROM identity.app_user WHERE username = ?', [Accounts::usernameOf(
                $email,
            )]))->toString();
    }

    private function export(string $account): int
    {
        $command = new Application(self::bootKernel())->find('app:privacy:export');

        return new CommandTester($command)->execute(['--account' => $account, '--output' => $this->file]);
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
