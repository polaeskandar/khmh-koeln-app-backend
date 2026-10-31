<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Migration\Command;

use App\Platform\Migration\Model\MigrationSet;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DiffCommandTest extends KernelTestCase
{
    #[DataProvider('contexts')]
    public function testTheMigratedSchemaMatchesTheMappingOfEveryContext(string $context): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:migrations:diff'));

        self::assertSame(Command::SUCCESS, $tester->execute(['context' => $context]));
        self::assertStringContainsString('needs no migration', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contexts(): iterable
    {
        foreach (MigrationSet::all(dirname(__DIR__, levels: 5)) as $set) {
            yield $set->schema => [$set->schema];
        }
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
