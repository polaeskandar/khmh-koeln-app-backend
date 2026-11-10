<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Privacy;

use App\Context\Learning\Infrastructure\Persistence\Repository\DoctrineAccountTraceRepository as LearningTraces;
use App\Context\Meetings\Infrastructure\Persistence\Repository\DoctrineAccountTraceRepository as MeetingTraces;
use App\Context\People\Infrastructure\Persistence\Repository\DoctrineAccountTraceRepository as PeopleTraces;
use App\Context\Privacy\Infrastructure\Persistence\Repository\DoctrineAccountTraceRepository as PrivacyTraces;
use App\Library\Types\Model\Type;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Console\Privacy\PrivacyFixture;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class PrivacyEraseAccountCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const array OWNED = [
        'people.guardian_relationship.user_id',
        'organization.registration_service.account_id',
        'organization.staff_assignment.account_id',
        'learning.lesson_collaborator.user_id',
    ];

    public function testAServantsWorkStaysWithoutTheServant(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        new PrivacyFixture(self::getContainer())->family();
        $servant = new Families(self::getContainer())->account('daniel@example.org');
        new RoleGrants(self::getContainer())->grant($servant, 'servant');
        $connection = new Database(self::getContainer())->connection();

        $columns = [
            ...MeetingTraces::ACTOR_COLUMNS,
            ...LearningTraces::ACTOR_COLUMNS,
        ];

        foreach ($columns as [$table, $column]) {
            $extra = $column === 'undone_by' ? ", undone_at = completed_at + interval '1 minute'" : '';
            $connection->executeStatement('UPDATE ' . $table . ' SET ' . $column . ' = ?' . $extra, [$servant]);
        }

        $connection->executeStatement(
            'INSERT INTO meetings.session_event (id, session_id, type, occurred_at, actor_id, by_generator) SELECT ?, id, ?, now(), ?, false FROM meetings.meeting_session',
            [Uuid::v7()->toRfc4122(), 'opened', $servant],
        );

        $before = $this->rowCounts();

        self::assertSame(Command::SUCCESS, $this->erase($servant));

        foreach ($columns as [$table, $column]) {
            self::assertSame(
                0,
                $connection->fetchOne('SELECT count(*) FROM ' . $table . ' WHERE ' . $column . ' = ?', [$servant]),
                $table . '.' . $column,
            );
        }

        self::assertSame($before, $this->rowCounts());
        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM identity.app_user WHERE id = ?', [$servant]));
    }

    public function testTheServantsAssignmentsGoWithTheAccount(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $servant = new Families(self::getContainer())->account('daniel@example.org');
        new RoleGrants(self::getContainer())->grant($servant, 'servant');
        $service = Structure::service('Choir');
        new Database(self::getContainer())->persisted($service);
        new ServedGroups(self::getContainer())->assign($servant, $service->id()->toRfc4122());

        self::assertSame(Command::SUCCESS, $this->erase($servant));

        self::assertSame(0, new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM organization.staff_assignment WHERE account_id = ?', [$servant]));
    }

    public function testAParentsReportsStayButItsRolesLinksAndPasswordLinksGo(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [, $parent] = new PrivacyFixture(self::getContainer())->family();
        $connection = new Database(self::getContainer())->connection();

        $connection->insert('identity.password_token', [
            'id' => Uuid::v7()->toRfc4122(),
            'token_hash' => 'h',
            'expires_at' => '2026-11-09 12:00:00+01',
            'user_id' => $parent,
            'created_at' => '2026-11-08 12:00:00+01',
        ]);

        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['account' => $parent]));

        self::assertStringContainsString(
            '1 roles, 1 guardian links and 1 password links removed',
            $tester->getDisplay(),
        );

        self::assertStringNotContainsString('hoda', $tester->getDisplay());

        self::assertSame(
            1,
            $connection->fetchOne('SELECT count(*) FROM learning.homework_completion WHERE completed_by IS NULL'),
        );

        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM identity.user_role WHERE user_id = ?', [
            $parent,
        ]));

        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM people.guardian_relationship WHERE user_id = ?', [
            $parent,
        ]));

        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM identity.password_token WHERE user_id = ?', [
            $parent,
        ]));
    }

    public function testEveryReferenceToAnAccountIsNulledOrDeleted(): void
    {
        $connection = new Database(self::getContainer())->connection();

        $foreignKeys = $connection->fetchFirstColumn(
            "SELECT conrelid::regclass::text || '.' || a.attname::text || '=' || confdeltype::text FROM pg_constraint c
             JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
             WHERE c.contype = 'f' AND c.confrelid = 'identity.app_user'::regclass",
        );

        foreach (array_map(static fn (mixed $key): string => Type::of($key)->toString(), $foreignKeys) as $key) {
            self::assertMatchesRegularExpression('/=[nc]$/', $key, $key . ' is neither SET NULL nor CASCADE');
        }

        $covered = array_map(static fn (array $actor): string => $actor[0] . '.' . $actor[1], [
            ...MeetingTraces::ACTOR_COLUMNS,
            ...LearningTraces::ACTOR_COLUMNS,
            ...PeopleTraces::ACTOR_COLUMNS,
            ...PrivacyTraces::ACTOR_COLUMNS,
            ...PrivacyTraces::SUBJECT_COLUMNS,
        ]);

        $references = $connection->fetchFirstColumn(
            "SELECT table_schema || '.' || table_name || '.' || column_name FROM information_schema.columns
             WHERE data_type = 'uuid' AND table_schema NOT IN ('public', 'identity')
               AND (column_name LIKE '%\\_by' OR column_name IN ('actor_id', 'responsible_id', 'user_id', 'account_id'))",
        );

        foreach (array_map(static fn (mixed $column): string => Type::of(
            $column,
        )->toString(), $references) as $reference) {
            self::assertContains(
                $reference,
                [...$covered, ...self::OWNED],
                $reference . ' points at an account but the erasure ignores it',
            );
        }
    }

    public function testTheLastAdministratorAndADryRunChangeNothing(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [, $parent] = new PrivacyFixture(self::getContainer())->family();
        $admin = new Families(self::getContainer())->account('mariam@example.org');
        new RoleGrants(self::getContainer())->grant($admin, 'administrator');
        $before = $this->rowCounts();

        self::assertSame(Command::FAILURE, $this->erase($admin));
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:privacy:erase-account:dry-run'));
        self::assertSame(Command::SUCCESS, $tester->execute(['account' => $parent]));
        self::assertStringContainsString('Dry run, nothing changed', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $this->erase(Uuid::v7()->toRfc4122()));
        self::assertSame(Command::FAILURE, $this->erase('not-an-account'));

        self::assertSame($before, $this->rowCounts());

        self::assertSame(
            2,
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT count(*) FROM identity.app_user'),
        );
    }

    private function erase(string $account): int
    {
        return $this->tester()->execute(['account' => $account]);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:privacy:erase-account'));
    }

    /**
     * @return list<int>
     */
    private function rowCounts(): array
    {
        $connection = new Database(self::getContainer())->connection();

        return array_map(static function (string $table) use ($connection): int {
            $count = $connection->fetchOne('SELECT count(*) FROM ' . $table);
            self::assertIsInt($count);

            return $count;
        }, [
            'meetings.attendance',
            'meetings.meeting_session',
            'meetings.session_event',
            'learning.lesson',
            'learning.homework_assignment',
            'learning.homework_completion',
            'people.guardian_relationship',
        ]);
    }
}
