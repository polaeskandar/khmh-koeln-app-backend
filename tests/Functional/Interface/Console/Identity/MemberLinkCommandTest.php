<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class MemberLinkCommandTest extends KernelTestCase
{
    public function testAParentLinkedToHerOwnRecordKeepsHerRoleAndLinkingAgainChangesNothing(): void
    {
        $hoda = $this->account('hoda@example.org');
        new RoleGrants(self::getContainer())->grant($hoda, 'parent');

        $record = new Families(self::getContainer())
            ->child('Hoda')
            ->id()
            ->toRfc4122();

        self::assertSame(Command::SUCCESS, $this->link('hoda@example.org', $record, [
            '--age' => '16-or-older',
            '--note' => 'Ignored',
        ]));

        $first = $this->member('hoda@example.org');
        self::assertSame(Command::SUCCESS, $this->link('hoda@example.org', $record, ['--age' => 'guardian-consent']));

        self::assertSame([$record, 'sixteenOrOlder', null], $first);
        self::assertSame($first, $this->member('hoda@example.org'));

        self::assertSame(
            ['parent'],
            new Database(self::getContainer())
                ->connection()
                ->fetchFirstColumn('SELECT r.name_key FROM identity.user_role ur JOIN identity.role r ON r.id = ur.role_id WHERE ur.user_id = ?', [
                    $hoda,
                ]),
        );
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('refusedAccounts')]
    public function testOnlyApprovedEnabledAccountsAreLinked(array $columns): void
    {
        $this->account('nevine@example.org', $columns);

        $record = new Families(self::getContainer())
            ->child('Nevine')
            ->id()
            ->toRfc4122();

        self::assertSame(Command::FAILURE, $this->link('nevine@example.org', $record, ['--age' => '16-or-older']));
        self::assertSame([null, null, null], $this->member('nevine@example.org'));
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function refusedAccounts(): iterable
    {
        yield 'pending' => [['approved_at' => null]];
        yield 'disabled' => [['disabled_at' => '2026-11-01 10:00:00+01']];
    }

    public function testInactiveUnknownAndTakenRecordsAndAMissingAgeAreRefused(): void
    {
        $this->account('nevine@example.org');
        $families = new Families(self::getContainer());
        $inactive = $families->child('Bishoy')->id()->toRfc4122();
        $taken = $families->child('Samir')->id()->toRfc4122();
        $nevine = $families->child('Nevine')->id()->toRfc4122();
        $connection = new Database(self::getContainer())->connection();
        $connection->update('people.person', ['status' => 'inactive'], ['id' => $inactive]);
        $this->account('samir@example.org', ['person_id' => $taken, 'age_check' => 'sixteenOrOlder']);

        foreach ([$inactive, Uuid::v7()->toRfc4122(), 'not-an-id', $taken] as $record) {
            self::assertSame(Command::FAILURE, $this->link('nevine@example.org', $record, ['--age' => '16-or-older']));
        }

        self::assertSame(Command::INVALID, $this->link('nevine@example.org', $nevine, []));

        self::assertSame(Command::FAILURE, $this->link('nobody@example.org', $taken, ['--age' => '16-or-older']));
        self::assertSame([null, null, null], $this->member('nevine@example.org'));
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(string $email, array $columns = []): string
    {
        $id = Uuid::v7()->toRfc4122();

        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row(['id' => $id, 'email' => $email, ...$columns]));

        return $id;
    }

    /**
     * @param array<string, string> $options
     */
    private function link(string $email, string $personId, array $options): int
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:member:link'));

        return $tester->execute(['email' => $email, 'personId' => $personId, ...$options]);
    }

    /**
     * @return list<mixed>
     */
    private function member(string $email): array
    {
        $row = new Database(self::getContainer())
            ->connection()
            ->fetchAssociative('SELECT person_id::text, age_check, guardian_consent_note FROM identity.app_user WHERE email = ?', [
                $email,
            ]);

        self::assertIsArray($row);

        return array_values($row);
    }
}
