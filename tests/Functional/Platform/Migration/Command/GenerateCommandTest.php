<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Migration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateCommandTest extends KernelTestCase
{
    private const string GENERATED = '/"([^"]+\/Learning\/Infrastructure\/Persistence\/Migrations\/Version\d{14}\.php)"/';

    public function testItCreatesAnEmptyMigrationInTheContextsFolder(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:migrations:generate'));

        self::assertSame(Command::SUCCESS, $tester->execute(['context' => 'learning']));

        $source = self::takeGeneratedFile($tester->getDisplay());

        self::assertStringContainsString(
            'namespace App\Context\Learning\Infrastructure\Persistence\Migrations;',
            $source,
        );

        self::assertStringContainsString('public function up(Schema $schema): void', $source);
    }

    private static function takeGeneratedFile(string $display): string
    {
        $match = [];
        self::assertSame(1, preg_match(self::GENERATED, $display, $match));
        $file = $match[1] ?? '';
        $source = (string) file_get_contents($file);
        unlink($file);

        return $source;
    }
}
