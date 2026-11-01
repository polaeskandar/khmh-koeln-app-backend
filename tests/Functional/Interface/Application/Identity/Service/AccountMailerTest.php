<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Application\Identity\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Interface\Application\Identity\Service\AccountMailer;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use Monolog\Level;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

final class AccountMailerTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private const string DSN = 'MAILER_DSN';

    #[Override]
    protected function tearDown(): void
    {
        $_ENV[self::DSN] = 'null://null';
        $_SERVER[self::DSN] = 'null://null';
        parent::tearDown();
    }

    #[DataProvider('languages')]
    public function testTheEmailIsInTheAccountsLanguageAndLeavesAfterTheResponse(
        string $language,
        string $subject,
        string $direction,
    ): void {
        $this->mailer()->send($this->account($language), 'test');

        self::assertEmailCount(0);
        $this->terminate();
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame($subject, $email->getSubject());

        self::assertStringContainsString(
            'lang="' . $language . '" dir="' . $direction . '"',
            (string) $email->getHtmlBody(),
        );

        self::assertNotSame('', trim((string) $email->getTextBody()));
        self::assertSame('sara@example.org', ($email->getTo()[0] ?? null)?->getAddress());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function languages(): iterable
    {
        yield 'Arabic' => ['ar', 'رسالة تجريبية من KHMK كولونيا', 'rtl'];
        yield 'German' => ['de', 'Testnachricht von KHMK Köln', 'ltr'];
        yield 'English' => ['en', 'Test message from KHMK Köln', 'ltr'];
    }

    public function testAFailingTransportRaisesNothingAndLogsWithoutTheAddress(): void
    {
        $_ENV[self::DSN] = 'smtp://127.0.0.1:1';
        $_SERVER[self::DSN] = 'smtp://127.0.0.1:1';
        self::ensureKernelShutdown();
        self::bootKernel();

        $this->mailer()->send($this->account('de'), 'test');
        $this->terminate();

        $errors = new Logs(self::getContainer())->atOrAbove(Level::Error);
        self::assertCount(1, $errors);
        $error = $errors[0] ?? null;
        self::assertNotNull($error);
        self::assertSame('Sending an email failed.', $error->message);
        self::assertStringNotContainsString('sara@example.org', json_encode($error->context, JSON_THROW_ON_ERROR));
    }

    private function account(string $language): User
    {
        $id = Uuid::v7()->toRfc4122();

        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => $id,
                'language' => $language,
            ]));

        $users = self::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $user = $users->find(Uuid::fromString($id));
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function terminate(): void
    {
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        $dispatcher->dispatch(
            new TerminateEvent($kernel, Request::create('/'), new Response()),
            KernelEvents::TERMINATE,
        );
    }

    private function mailer(): AccountMailer
    {
        $mailer = self::getContainer()->get(AccountMailer::class);
        self::assertInstanceOf(AccountMailer::class, $mailer);

        return $mailer;
    }
}
