<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Migration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrateCommandTest extends KernelTestCase
{
    public function testItMigratesEveryContextInItsOwnSchema(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:migrations:migrate'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        foreach (['identity', 'organization', 'people', 'scheduling', 'learning'] as $schema) {
            self::assertStringContainsString(sprintf('(schema "%s")', $schema), $tester->getDisplay());
        }
    }
}
