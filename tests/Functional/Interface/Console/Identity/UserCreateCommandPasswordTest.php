<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\StoredAccounts;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserCreateCommandPasswordTest extends KernelTestCase
{
    private const string PIPED = 'correct horse battery';

    public function testNoPasswordWinsOverStandardInput(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:user:create'));
        $tester->setInputs([self::PIPED]);

        self::assertSame(Command::SUCCESS, $tester->execute([
            'email' => 'sara@example.org',
            'name' => 'Sara',
            '--no-password' => true,
            '--password-stdin' => true,
        ], ['interactive' => false]));

        $accounts = new StoredAccounts(self::getContainer());

        self::assertFalse($accounts->acceptsSecret($accounts->withEmail('sara@example.org'), self::PIPED));
    }
}
