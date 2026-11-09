<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Context\Identity\Domain\Entity\StoredAccounts;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserCreateCommandTest extends KernelTestCase
{
    private const string TYPED = 'correct horse battery';

    public function testATypedPasswordCreatesAnApprovedAccount(): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED, self::TYPED]);

        self::assertSame(Command::SUCCESS, $tester->execute([
            'email' => ' Sara.Hanna@Example.org',
            'name' => 'Sara Hanna',
        ]));

        $account = $this->accounts()->withEmail('sara.hanna@example.org');
        self::assertTrue($account->status()->isActive());
        self::assertSame(['ROLE_USER'], SecurityUser::of($account)->getRoles());
        self::assertTrue($this->accounts()->acceptsSecret($account, self::TYPED));
        self::assertStringNotContainsString(self::TYPED, $tester->getDisplay());
    }

    public function testAPasswordFromStandardInputIsUsed(): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED]);

        $status = $tester->execute([
            'email' => 'sara@example.org',
            'name' => 'Sara',
            '--password-stdin' => true,
            '--language' => 'ar',
        ], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $status);

        self::assertTrue($this->accounts()->acceptsSecret(
            $this->accounts()->withEmail('sara@example.org'),
            self::TYPED,
        ));
    }

    public function testWithoutAPasswordNothingIsAskedAndNoPasswordWorks(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([
            'email' => 'sara@example.org',
            'name' => 'Sara',
            '--no-password' => true,
        ]));

        self::assertStringNotContainsString('Password', $tester->getDisplay());
        self::assertFalse($this->accounts()->acceptsSecret($this->accounts()->withEmail('sara@example.org'), ''));
    }

    /**
     * @param list<string> $typed
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedPasswords')]
    public function testARefusedPasswordWritesNothingAndIsNeverShown(array $typed): void
    {
        $tester = $this->tester();
        $tester->setInputs($typed);

        self::assertNotSame(Command::SUCCESS, $tester->execute(['email' => 'sara@example.org', 'name' => 'Sara']));

        self::assertSame(0, $this->accounts()->count());

        foreach ($typed as $secret) {
            self::assertStringNotContainsString($secret, $tester->getDisplay());
        }
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function refusedPasswords(): iterable
    {
        yield 'nine characters' => [['nine char', 'nine char']];
        yield 'mismatched entries' => [[self::TYPED, 'correct horse staple']];
    }

    public function testATakenEmailInAnyCaseWritesNothing(): void
    {
        $this->tester()->execute(['email' => 'sara@example.org', 'name' => 'Sara', '--no-password' => true]);
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute([
            'email' => 'SARA@Example.org',
            'name' => 'Sara',
            '--no-password' => true,
        ]));

        self::assertStringContainsString('accounts.emailTaken', $tester->getDisplay());
        self::assertSame(1, $this->accounts()->count());
    }

    public function testAnUnknownLanguageWritesNothing(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute([
            'email' => 'sara@example.org',
            'name' => 'Sara',
            '--no-password' => true,
            '--language' => 'fr',
        ]));

        self::assertStringContainsString('language: accounts.chooseLanguage', $tester->getDisplay());
        self::assertSame(0, $this->accounts()->count());
    }

    public function testWithoutATerminalThePasswordMustComeFromStandardInput(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'sara@example.org', 'name' => 'Sara'], [
            'interactive' => false,
        ]));

        self::assertStringContainsString('standard input', $tester->getDisplay());
        self::assertSame(0, $this->accounts()->count());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:user:create'));
    }

    private function accounts(): StoredAccounts
    {
        return new StoredAccounts(self::getContainer());
    }
}
