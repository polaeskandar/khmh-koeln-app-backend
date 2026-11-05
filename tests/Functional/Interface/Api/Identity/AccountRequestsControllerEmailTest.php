<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Auth\Registrations;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;

final class AccountRequestsControllerEmailTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    private const string DSN = 'MAILER_DSN';

    private const string PARENT_ROLE = '019a3f00-0000-7000-8000-000000000004';

    private const string SERVANT_ROLE = '019a3f00-0000-7000-8000-000000000003';

    #[Override]
    protected function tearDown(): void
    {
        $_ENV[self::DSN] = 'null://null';
        $_SERVER[self::DSN] = 'null://null';
        parent::tearDown();
    }

    public function testAnApprovedParentOrServantGetsOneEmailWithTheSignInLink(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());
        $this->jsonRequest('POST', '/api/auth/register', Registrations::servant($this->serviceIds()));

        $mina = new Families(self::getContainer())
            ->child('Mina')
            ->id()
            ->toRfc4122();

        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->approve('mariam.hanna@example.org', [
            'as' => 'parent',
            'childIds' => [$mina],
            'roleIds' => [self::PARENT_ROLE],
        ]);

        $this->assertNotice(
            'mariam.hanna@example.org',
            'حسابك في KHMK كولونيا جاهز',
            'https://app.khmk-koeln.com/ar/sign-in',
        );

        $this->approve('daniel@example.org', ['as' => 'servant', 'roleIds' => [self::SERVANT_ROLE]]);

        $this->assertNotice(
            'daniel@example.org',
            'حسابك في KHMK كولونيا جاهز',
            'https://app.khmk-koeln.com/ar/sign-in',
        );
    }

    public function testAFailingEmailLeavesTheApprovalInPlace(): void
    {
        $_ENV[self::DSN] = 'smtp://127.0.0.1:1';
        $_SERVER[self::DSN] = 'smtp://127.0.0.1:1';
        $this->startClient([]);
        $this->jsonRequest('POST', '/api/auth/register', Registrations::servant($this->serviceIds()));
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->approve('daniel@example.org', ['as' => 'servant', 'roleIds' => [self::SERVANT_ROLE]]);

        self::assertResponseStatusCodeSame(200);

        self::assertSame(
            1,
            new Database(self::getContainer())
                ->connection()
                ->fetchOne(
                    "SELECT count(*) FROM identity.app_user WHERE email = 'daniel@example.org' AND approved_at IS NOT NULL",
                ),
        );
    }

    public function testDecliningSendsNothing(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::servant($this->serviceIds()));
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/account-requests/' . $this->idOf('daniel@example.org') . '/decline');

        self::assertResponseStatusCodeSame(204);
        self::assertEmailCount(0);
    }

    /**
     * @param array<string, string|list<string>> $body
     */
    private function approve(string $email, array $body): void
    {
        $this->jsonRequest('POST', '/api/account-requests/' . $this->idOf($email) . '/approve', $body);

        self::assertResponseIsSuccessful();
    }

    private function assertNotice(string $email, string $subject, string $url): void
    {
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame($email, ($message->getTo()[0] ?? null)?->getAddress());
        self::assertSame($subject, $message->getSubject());
        self::assertStringContainsString($url, (string) $message->getTextBody());
    }

    /**
     * @return list<string>
     */
    private function serviceIds(): array
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);

        return [$service->id()->toRfc4122()];
    }

    private function idOf(string $email): string
    {
        $id = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT id FROM identity.app_user WHERE email = ?', [$email]);

        self::assertIsString($id);

        return $id;
    }
}
