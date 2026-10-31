<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Migration\Command;

use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckCommandTest extends KernelTestCase
{
    public function testAMigratedDatabaseMatchesEveryMapping(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester()->execute([]));
    }

    public function testAColumnWithoutAMigrationIsReportedWithItsContext(): void
    {
        $tester = $this->tester();

        new Database(self::getContainer())
            ->connection()
            ->executeStatement('ALTER TABLE organization.stage ADD COLUMN colour TEXT');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('organization', $tester->getDisplay());
        self::assertStringNotContainsString('identity', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:migrations:check'));
    }
}
