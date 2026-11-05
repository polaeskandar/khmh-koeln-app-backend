<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Library\Checksum\Model\Checksum;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

final class UserPasswordLinkCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use MailerAssertionsTrait;

    private const string LINK = '#https://app\.khmk-koeln\.com/de/set-password/\#token=([A-Za-z0-9_-]{43})\s#';

    public function testTheLinkIsEmailedAndItsTokenIsStoredOnlyAsAHash(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->account([]);

        $tester = $this->tester();
        $token = $this->emailedToken($tester);

        $row = $this
            ->database()
            ->connection()
            ->fetchAssociative(
                "SELECT token_hash, expires_at = '2026-11-09T10:00:00+01:00'::timestamptz AS in_a_day FROM identity.password_token",
            );

        self::assertSame(['token_hash' => Checksum::sha256($token), 'in_a_day' => true], $row);
        self::assertStringContainsString('09.11.2026 10:00', $this->emailText());
        self::assertStringNotContainsString($token, $tester->getDisplay());
        self::assertSame([], new Logs(self::getContainer())->containing($token));
    }

    public function testASecondLinkReplacesTheFirst(): void
    {
        $this->account([]);

        $this->emailedToken($this->tester());
        $second = $this->emailedToken($this->tester());

        self::assertSame(
            [Checksum::sha256($second)],
            $this->database()->connection()->fetchFirstColumn('SELECT token_hash FROM identity.password_token'),
        );
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('refusals')]
    public function testOnlyApprovedEnabledAccountsGetALink(string $email, array $columns, string $code): void
    {
        $this->account($columns);
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $this->linkFor($tester, $email));

        self::assertStringContainsString($code, $tester->getDisplay());
        self::assertEmailCount(0);
        self::assertSame(0, $this->database()->connection()->fetchOne('SELECT count(*) FROM identity.password_token'));
    }

    /**
     * @return iterable<string, array{string, array<string, string|null>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'unknown email' => ['nobody@example.org', [], 'notFound.account'];
        yield 'pending' => ['daniel@example.org', ['approved_at' => null], 'accounts.passwordLinkUnavailable'];
        yield 'disabled' => [
            'daniel@example.org',
            ['disabled_at' => '2026-11-09 12:00:00+01'],
            'accounts.passwordLinkUnavailable',
        ];
    }

    private function emailedToken(ApplicationTester $tester): string
    {
        self::assertSame(Command::SUCCESS, $this->linkFor($tester, 'daniel@example.org'));
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage() ?? new Email(), 'To', 'daniel@example.org');

        $matches = [];
        self::assertSame(1, preg_match(self::LINK, $this->emailText(), $matches));

        return $matches[1] ?? '';
    }

    private function emailText(): string
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        $text = $email->getTextBody();
        self::assertIsString($text);

        return $text;
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(array $columns): void
    {
        $this
            ->database()
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => Uuid::v7()->toRfc4122(),
                'email' => 'daniel@example.org',
                ...$columns,
            ]));
    }

    private function linkFor(ApplicationTester $tester, string $email): int
    {
        return $tester->run(['command' => 'app:user:password-link', 'email' => $email]);
    }

    private function tester(): ApplicationTester
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
