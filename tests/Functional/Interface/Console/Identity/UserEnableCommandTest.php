<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class UserEnableCommandTest extends KernelTestCase
{
    public function testADisabledAccountIsEnabledAndKeepsItsVersion(): void
    {
        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => Uuid::v7()->toRfc4122(),
                'email' => 'daniel@example.org',
                'disabled_at' => '2026-11-09 12:00:00+01',
                'auth_version' => '3',
            ]));

        $tester = new CommandTester(new Application(self::bootKernel())->find('app:user:enable'));

        self::assertSame(Command::SUCCESS, $tester->execute(['username' => 'daniel']));

        self::assertSame(
            ['disabled_at' => null, 'auth_version' => 3],
            new Database(self::getContainer())
                ->connection()
                ->fetchAssociative('SELECT disabled_at, auth_version FROM identity.app_user'),
        );
    }
}
