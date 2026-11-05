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

final class MemberUnlinkCommandTest extends KernelTestCase
{
    public function testUnlinkingClearsTheLinkAndIsIdempotent(): void
    {
        $connection = new Database(self::getContainer())->connection();

        $connection->insert('identity.app_user', Accounts::row([
            'email' => 'mina@example.org',
            'person_id' => Uuid::v7()->toRfc4122(),
            'age_check' => 'guardianConsent',
            'guardian_consent_note' => 'Father agreed.',
        ]));

        $tester = new CommandTester(new Application(self::bootKernel())->find('app:member:unlink'));

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'mina@example.org']));
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'mina@example.org']));
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'nobody@example.org']));

        self::assertSame(
            ['person_id' => null, 'age_check' => null, 'guardian_consent_note' => null],
            $connection->fetchAssociative('SELECT person_id, age_check, guardian_consent_note FROM identity.app_user'),
        );
    }
}
