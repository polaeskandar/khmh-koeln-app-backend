<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\People;

use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GuardianRevokeCommandTest extends KernelTestCase
{
    public function testTheLinksAreListedAndARevokeIsIdempotent(): void
    {
        $families = new Families(self::getContainer());
        $account = $families->account('mariam@example.org', [], 'parent');
        $child = $families->child();
        $families->link($account, $child);
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:guardian:revoke'));

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'mariam@example.org']));
        self::assertStringContainsString($child->id()->toRfc4122(), $tester->getDisplay());
        self::assertStringContainsString('Kirollos Iskander', $tester->getDisplay());

        foreach ([1, 2] as $run) {
            self::assertSame(
                Command::SUCCESS,
                $tester->execute([
                    'email' => 'mariam@example.org',
                    'personId' => $child->id()->toRfc4122(),
                ]),
                (string) $run,
            );
        }

        self::assertSame(
            ['revoked'],
            new Database(self::getContainer())
                ->connection()
                ->fetchFirstColumn('SELECT status FROM people.guardian_relationship'),
        );
    }

    public function testAnUnknownAccountFails(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:guardian:revoke'));

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'nobody@example.org']));
    }

    public function testAnUnknownChildFailsAndARevokeIsConfirmed(): void
    {
        $families = new Families(self::getContainer());
        $account = $families->account('mariam@example.org', [], 'parent');
        $child = $families->child();
        $families->link($account, $child);
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:guardian:revoke'));

        self::assertSame(Command::FAILURE, $tester->execute([
            'email' => 'mariam@example.org',
            'personId' => 'not-a-uuid',
        ]));

        self::assertStringContainsString('notFound.person', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute([
            'email' => 'mariam@example.org',
            'personId' => $child->id()->toRfc4122(),
        ]));

        self::assertStringContainsString('mariam@example.org no longer acts for the child.', $tester->getDisplay());
    }
}
