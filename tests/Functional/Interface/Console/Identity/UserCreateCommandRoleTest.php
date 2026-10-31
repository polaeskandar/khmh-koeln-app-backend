<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\StoredAccounts;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserCreateCommandRoleTest extends KernelTestCase
{
    private const string TYPED = 'correct horse battery';

    /**
     * @param array<string, bool> $passwordOptions
     */
    #[DataProvider('administrators')]
    public function testTheRoleOptionGivesTheAccountThatRole(array $passwordOptions): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED, self::TYPED]);

        self::assertSame(Command::SUCCESS, $tester->execute([
            'email' => 'admin@example.org',
            'name' => 'Admin',
            '--role' => 'administrator',
            ...$passwordOptions,
        ]));

        self::assertTrue(
            new StoredAccounts(self::getContainer())
                ->withEmail('admin@example.org')
                ->status()
                ->isActive(),
        );

        self::assertSame(
            ['Administrator'],
            new Database(self::getContainer())
                ->connection()
                ->fetchFirstColumn(
                    'SELECT r.name FROM identity.user_role ur JOIN identity.role r ON r.id = ur.role_id',
                ),
        );
    }

    /**
     * @return iterable<string, array{array<string, bool>}>
     */
    public static function administrators(): iterable
    {
        yield 'typed password' => [[]];
        yield 'no password' => [['--no-password' => true]];
    }

    public function testAnUnknownRoleWritesNothing(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute([
            'email' => 'sara@example.org',
            'name' => 'Sara',
            '--no-password' => true,
            '--role' => 'Pastor',
        ]));

        self::assertStringContainsString('role: roles.unknown', $tester->getDisplay());
        self::assertSame(0, new StoredAccounts(self::getContainer())->count());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:user:create'));
    }
}
