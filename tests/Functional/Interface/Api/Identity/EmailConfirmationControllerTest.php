<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Identity\EmailedLinks;
use App\Tests\Support\Interface\Api\Identity\Registrations;
use App\Tests\Support\JsonBody;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;

final class EmailConfirmationControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use MailerAssertionsTrait;

    private const string EMAIL = 'mariam.hanna@example.org';

    private const string INVALID = '{"status":422,"code":"auth.confirmationLinkInvalid","params":{}}';

    public function testARegistrationReachesTheOfficeOnlyAfterItsLink(): void
    {
        $token = $this->registered();
        $this->assertLastSubject('أكّد عنوان بريدك الإلكتروني لدى KHMK كولونيا');
        $this->signInWithRoles('admin@example.org', 'administrator');
        self::assertSame(0, $this->requestCount());

        $this->confirm($token);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->requestCount());

        $this->confirm($token);
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());
        $this->confirm('an unknown token');
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());
    }

    public function testALinkOlderThanSevenDaysIsInvalid(): void
    {
        self::mockTime('2026-11-01T10:00:00+01:00');
        $token = $this->registered();

        self::mockTime('2026-11-08T10:00:01+01:00');
        $this->confirm($token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());
    }

    public function testAConfirmedAddressIsRefusedAsTakenWithoutAnEmail(): void
    {
        new SignInAccounts(self::getContainer())->add(self::EMAIL, ['language' => 'en']);
        $hash = $this->passwordHash();

        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());

        self::assertResponseStatusCodeSame(422);
        $this->assertJsonPath('fieldErrors', ['account.email' => ['code' => 'accounts.emailTaken', 'params' => []]]);
        self::assertEmailCount(0);
        self::assertSame($hash, $this->passwordHash());

        self::assertSame(
            1,
            new Database(self::getContainer())
                ->connection()
                ->fetchOne("SELECT count(*) FROM identity.app_user WHERE email = 'mariam.hanna@example.org'"),
        );
    }

    public function testRegisteringAgainSendsAFreshLinkThatVoidsTheOld(): void
    {
        $older = $this->registered();
        $newer = $this->registered();

        $this->confirm($older);
        $this->assertProblem(422, 'auth.confirmationLinkInvalid');
        $this->confirm($newer);
        self::assertResponseStatusCodeSame(204);
    }

    public function testAnAddressGetsAtMostThreeMailsAnHour(): void
    {
        $this->browser()->disableReboot();
        $sent = [];

        foreach (range(start: 1, end: 4) as $attempt) {
            $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());
            self::assertResponseStatusCodeSame(202, 'attempt ' . $attempt);
            $sent[] = count(self::getMailerMessages());
        }

        self::assertSame([1, 1, 1, 0], $sent);
    }

    private function registered(): string
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());
        self::assertResponseStatusCodeSame(202);

        return new EmailedLinks(self::getContainer())->lastToken();
    }

    private function confirm(#[SensitiveParameter] string $token): void
    {
        $this->jsonRequest('POST', '/api/auth/email-confirmations', ['token' => $token]);
    }

    private function assertLastSubject(string $subject): void
    {
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame($subject, $message->getSubject());
        self::assertSame(self::EMAIL, ($message->getTo()[0] ?? null)?->getAddress());
    }

    private function requestCount(): int
    {
        $this->jsonRequest('GET', '/api/account-requests');

        return count(JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
    }

    private function passwordHash(): string
    {
        $hash = new Database(self::getContainer())
            ->connection()
            ->fetchOne("SELECT password_hash FROM identity.app_user WHERE email = 'mariam.hanna@example.org'");

        self::assertIsString($hash);

        return $hash;
    }
}
