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

    public function testATypedPasswordCreatesAnAccountThatSignsIn(): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED, self::TYPED]);

        self::assertSame(Command::SUCCESS, $tester->execute([
            'username' => 'Sara.Hanna',
            'email' => ' Sara.Hanna@Example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
        ]));

        $user = $this->accounts()->withEmail('sara.hanna@example.org');
        self::assertSame('sara.hanna', $user->username()->value);
        self::assertTrue($user->status()->isActive());
        self::assertSame(['ROLE_USER'], SecurityUser::of($user)->getRoles());
        self::assertTrue($this->accounts()->acceptsSecret($user, self::TYPED));
        self::assertStringNotContainsString(self::TYPED, $tester->getDisplay());
    }

    public function testAPasswordFromStandardInputIsUsed(): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED]);

        $status = $tester->execute([
            'username' => 'sara',
            'email' => 'sara@example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
            '--password-stdin' => true,
            '--language' => 'ar',
        ], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $status);

        self::assertTrue($this->accounts()->acceptsSecret(
            $this->accounts()->withEmail('sara@example.org'),
            self::TYPED,
        ));
    }

    /**
     * @param list<string> $typed
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedPasswords')]
    public function testARefusedPasswordWritesNothingAndIsNeverShown(array $typed): void
    {
        $tester = $this->tester();
        $tester->setInputs($typed);

        self::assertNotSame(Command::SUCCESS, $tester->execute([
            'username' => 'sara',
            'email' => 'sara@example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
        ]));

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
        $this->piped([
            'username' => 'sara',
            'email' => 'sara@example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
        ]);

        $tester = $this->tester();
        $tester->setInputs([self::TYPED]);

        self::assertSame(Command::INVALID, $tester->execute([
            'username' => 'sara2',
            'email' => 'SARA@Example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
            '--password-stdin' => true,
        ], ['interactive' => false]));

        self::assertStringContainsString('accounts.emailTaken', $tester->getDisplay());
        self::assertSame(1, $this->accounts()->count());
    }

    public function testATakenUsernameInAnyCaseWritesNothing(): void
    {
        $this->piped([
            'username' => 'sara',
            'email' => 'sara@example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
        ]);

        $tester = $this->tester();
        $tester->setInputs([self::TYPED]);

        self::assertSame(Command::FAILURE, $tester->execute([
            'username' => ' SARA ',
            'email' => 'sara.hanna@example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
            '--password-stdin' => true,
        ], ['interactive' => false]));

        self::assertStringContainsString('accounts.usernameTaken', $tester->getDisplay());
        self::assertSame(1, $this->accounts()->count());
    }

    public function testAnInvalidUsernameWritesNothing(): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED]);

        self::assertSame(Command::INVALID, $tester->execute([
            'username' => 'a..b',
            'email' => 'sara@example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
            '--password-stdin' => true,
        ], ['interactive' => false]));

        self::assertStringContainsString('username: accounts.usernameInvalid', $tester->getDisplay());
        self::assertSame(0, $this->accounts()->count());
    }

    public function testAnUnknownLanguageWritesNothing(): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED]);

        self::assertSame(Command::INVALID, $tester->execute([
            'username' => 'sara',
            'email' => 'sara@example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
            '--password-stdin' => true,
            '--language' => 'fr',
        ], ['interactive' => false]));

        self::assertStringContainsString('language: accounts.chooseLanguage', $tester->getDisplay());
        self::assertSame(0, $this->accounts()->count());
    }

    public function testWithoutATerminalThePasswordMustComeFromStandardInput(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute([
            'email' => 'sara@example.org',
            'firstName' => 'Sara',
            'lastName' => 'Hanna',
        ], [
            'interactive' => false,
        ]));

        self::assertStringContainsString('standard input', $tester->getDisplay());
        self::assertSame(0, $this->accounts()->count());
    }

    /**
     * @param array<string, string> $arguments
     */
    private function piped(array $arguments): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED]);
        $tester->execute([...$arguments, '--password-stdin' => true], ['interactive' => false]);
        $tester->assertCommandIsSuccessful();
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
