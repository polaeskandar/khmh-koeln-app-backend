<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Demo;

use App\Library\Types\Model\Type;
use App\Tests\Support\Context\Identity\Domain\Entity\StoredAccounts;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoSeedCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const array TABLES = [
        'identity.app_user',
        'identity.user_role',
        'organization.service',
        'organization.stage',
        'people.person',
        'people.membership',
        'meetings.meeting_schedule',
        'meetings.meeting_session',
        'meetings.session_event',
        'people.guardian_relationship',
        'learning.lesson',
        'learning.homework_assignment',
        'learning.homework_completion',
        'attendance.attendance',
    ];

    public function testLoadingTwiceGivesTheSameRows(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');

        self::assertSame(Command::SUCCESS, $this->seed());
        $first = $this->counts();
        self::assertSame(Command::SUCCESS, $this->seed());

        self::assertSame($first, $this->counts());
        self::assertSame([5, 4, 2, 6, 94, 94, 8, 8, 11, 2, 2, 2, 1, 19], $first);
        self::assertSame(4, $this->number('SELECT count(*) FROM identity.role'));
    }

    public function testTheAccountsHaveTheirRolesAndTheDemoPassword(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $this->seed();

        self::assertSame([
            ['christine@example.org', true,  'service_leader'],
            ['daniel@example.org',    true,  'servant'],
            ['hoda@example.org',      true,  'parent'],
            ['mariam@example.org',    true,  'administrator'],
            ['nadia@example.org',     false, null],
        ], $this->rows(
            'SELECT u.email, u.approved_at IS NOT NULL, r.name_key FROM identity.app_user u '
            . 'LEFT JOIN identity.user_role ur ON ur.user_id = u.id LEFT JOIN identity.role r ON r.id = ur.role_id '
            . 'ORDER BY u.email',
        ));

        $accounts = new StoredAccounts(self::getContainer());

        $secret = Type::of($_SERVER['DEMO_PASSWORD'] ?? null)->toString();

        foreach (['christine', 'daniel', 'hoda', 'mariam', 'nadia'] as $name) {
            self::assertTrue($accounts->acceptsSecret($accounts->withEmail($name . '@example.org'), $secret));
        }
    }

    public function testThirdClassHasFourteenChildrenAndEachDayASession(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $this->seed();

        self::assertSame(14, $this->number(
            'SELECT count(*) FROM people.membership m JOIN organization.stage s ON s.id = m.stage_id '
            . "WHERE s.name = '3rd Class' AND m.ends_on IS NULL",
        ));

        self::assertSame(
            2,
            $this->number("SELECT count(*) FROM people.person WHERE first_name = 'Mina' AND last_name = 'Hanna'"),
        );

        self::assertSame([
            ['2026-11-01', 'Sunday class', '12:00:00'],
            ['2026-11-07', '3rd Class practice', '17:00:00'],
            ...array_fill(start_index: 0, count: 6, value: ['2026-11-08', 'Sunday class', '12:00:00']),
        ], $this->rows(
            'SELECT s.occurrence_date::text, sc.name, sc.start_time::text FROM meetings.meeting_session s '
            . 'JOIN meetings.meeting_schedule sc ON sc.id = s.schedule_id ORDER BY 1, 2',
        ));
    }

    private function seed(): int
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:demo:seed'))->execute([]);
    }

    /**
     * @return list<int>
     */
    private function counts(): array
    {
        return array_map(fn (string $table): int => $this->number('SELECT count(*) FROM ' . $table), self::TABLES);
    }

    private function number(string $sql): int
    {
        $number = new Database(self::getContainer())
            ->connection()
            ->fetchOne($sql);

        self::assertIsInt($number);

        return $number;
    }

    /**
     * @return list<list<mixed>>
     */
    private function rows(string $sql): array
    {
        return new Database(self::getContainer())
            ->connection()
            ->fetchAllNumeric($sql);
    }
}
