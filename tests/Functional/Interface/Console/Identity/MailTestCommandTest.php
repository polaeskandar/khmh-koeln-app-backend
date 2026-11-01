<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Uid\Uuid;

final class MailTestCommandTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    public function testAKnownAccountGetsOneTestEmailWhenTheCommandEnds(): void
    {
        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => Uuid::v7()->toRfc4122(),
            ]));

        self::assertSame(Command::SUCCESS, $this->tester()->run([
            'command' => 'app:mail:test',
            'email' => 'sara@example.org',
        ]));

        self::assertEmailCount(1);
    }

    public function testAnUnknownAccountFails(): void
    {
        self::assertSame(Command::FAILURE, $this->tester()->run([
            'command' => 'app:mail:test',
            'email' => 'nobody@example.org',
        ]));

        self::assertEmailCount(0);
    }

    private function tester(): ApplicationTester
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }
}
