<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Migration\Command;

use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DiffCommandTest extends KernelTestCase
{
    public function testAContextWithoutChangesNeedsNoMigration(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:migrations:diff'));

        self::assertSame(Command::SUCCESS, $tester->execute(['context' => 'people']));
        self::assertStringContainsString('needs no migration', $tester->getDisplay());
    }

    public function testItRejectsAnUnknownContext(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:migrations:diff'));

        try {
            $tester->execute(['context' => 'billing']);
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Unknown context "billing".', $exception->getMessage());

            return;
        }

        self::fail('An unknown context was accepted.');
    }
}
