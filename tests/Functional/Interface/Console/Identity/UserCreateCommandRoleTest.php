<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\StoredAccounts;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserCreateCommandRoleTest extends KernelTestCase
{
    private const string TYPED = 'correct horse battery';

    public function testTheRoleOptionGivesTheAccountThatRole(): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED, self::TYPED]);

        self::assertSame(Command::SUCCESS, $tester->execute([
            'username' => 'admin',
            'email' => 'admin@example.org',
            'name' => 'Admin',
            '--role' => 'administrator',
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

    public function testAnUnknownRoleWritesNothing(): void
    {
        $tester = $this->tester();
        $tester->setInputs([self::TYPED]);

        self::assertSame(Command::INVALID, $tester->execute([
            'username' => 'sara',
            'email' => 'sara@example.org',
            'name' => 'Sara',
            '--password-stdin' => true,
            '--role' => 'Pastor',
        ], ['interactive' => false]));

        self::assertStringContainsString('role: roles.unknown', $tester->getDisplay());
        self::assertSame(0, new StoredAccounts(self::getContainer())->count());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:user:create'));
    }
}
