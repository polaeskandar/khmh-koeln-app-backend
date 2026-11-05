<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class UserPurgeUnconfirmedCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testOnlyUnconfirmedRegistrationsOlderThanAWeekGo(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $stale = $this->account('stale@example.org', '2026-11-01 11:59:00+01', confirmed: null, approved: null);
        $this->account('recent@example.org', '2026-11-02 12:00:00+01', confirmed: null, approved: null);

        $this->account(
            'confirmed@example.org',
            '2026-10-01 12:00:00+01',
            confirmed: '2026-10-01 12:05:00+01',
            approved: null,
        );

        $this->account(
            'approved@example.org',
            '2026-10-01 12:00:00+01',
            confirmed: null,
            approved: '2026-10-02 12:00:00+01',
        );

        $connection = new Database(self::getContainer())->connection();

        $connection->executeStatement('INSERT INTO organization.registration_service (account_id, service_id) SELECT ?, id FROM organization.service LIMIT 1', [
            $stale,
        ]);

        $tester = new CommandTester(new Application(self::bootKernel())->find('app:user:purge-unconfirmed'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Deleted 1 unconfirmed registrations.', $tester->getDisplay());
        self::assertStringNotContainsString('@', $tester->getDisplay());

        self::assertSame(
            ['approved@example.org', 'confirmed@example.org', 'recent@example.org'],
            $connection->fetchFirstColumn('SELECT email FROM identity.app_user ORDER BY email'),
        );

        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM organization.registration_service WHERE account_id = ?', [
            $stale,
        ]));
    }

    public function testAResentLinkKeepsTheRegistrationUntilTheLinkExpires(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $resent = $this->account('resent@example.org', '2026-10-31 12:00:00+01', confirmed: null, approved: null);

        new Database(self::getContainer())
            ->connection()
            ->insert('identity.email_confirmation_token', [
                'id' => Uuid::v7()->toRfc4122(),
                'user_id' => $resent,
                'token_hash' => str_repeat('a', times: 64),
                'created_at' => '2026-11-06 12:00:00+01',
                'expires_at' => '2026-11-13 12:00:00+01',
            ]);

        self::assertSame(0, $this->purged());

        self::mockTime('2026-11-13T12:00:01+01:00');
        self::assertSame(1, $this->purged());
    }

    private function purged(): int
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:user:purge-unconfirmed'));
        $tester->execute([]);

        return (int) preg_replace('/\D+/', replacement: '', subject: $tester->getDisplay());
    }

    private function account(string $email, string $createdAt, ?string $confirmed, ?string $approved): string
    {
        $id = Uuid::v7()->toRfc4122();

        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => $id,
                'email' => $email,
                'created_at' => $createdAt,
                'email_confirmed_at' => $confirmed,
                'approved_at' => $approved,
            ]));

        return $id;
    }
}
