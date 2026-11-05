<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Demo;

use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoSeedCommandScenarioTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testLastSundaysLessonWasTaughtWithHomeworkHodaReportedForMina(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $this->seed();

        self::assertSame([
            ['The Lost Sheep',        'completed', '2026-11-01', 'Luke 15:3–7',  'Daniel Michael'],
            ['Zacchaeus Meets Jesus', 'ready',     '2026-11-08', 'Luke 19:1–10', 'Daniel Michael'],
        ], $this->rows(
            'SELECT l.title, l.status, l.teaching_date::text, l.passage, u.display_name FROM learning.lesson l '
            . 'JOIN identity.app_user u ON u.id = l.responsible_id JOIN organization.stage s ON s.id = l.stage_id '
            . "WHERE s.name = '3rd Class' ORDER BY l.teaching_date",
        ));

        self::assertSame([
            ['Learn the memory verse',  'draft',     false, null,         0],
            ['Read the story together', 'published', true,  '2026-11-07', 1],
        ], $this->rows(
            'SELECT h.title, h.status, h.published_at IS NOT NULL, h.due_date::text, '
            . '(SELECT count(*) FROM learning.homework_completion c WHERE c.assignment_id = h.id AND c.undone_at IS NULL)::int '
            . "FROM learning.homework_assignment h JOIN organization.stage s ON s.id = h.stage_id WHERE s.name = '3rd Class' "
            . 'ORDER BY h.title',
        ));

        self::assertSame([['Mina', 'Hanna', '3rd Class', 'hoda@example.org']], $this->rows(
            'SELECT p.first_name, p.last_name, s.name, u.email FROM learning.homework_completion c '
            . 'JOIN people.person p ON p.id = c.person_id JOIN identity.app_user u ON u.id = c.completed_by '
            . 'JOIN people.membership m ON m.person_id = p.id JOIN organization.stage s ON s.id = m.stage_id '
            . "WHERE s.name = '3rd Class'",
        ));
    }

    public function testLastSundayWasClosedWithElevenMarksAndTodayIsOpenWithSeven(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $this->seed();

        self::assertSame([
            ['2026-11-01', 'Sunday class',       'completed', 11, 1],
            ['2026-11-07', '3rd Class practice', 'active',    7,  0],
        ], $this->rows(
            'SELECT s.occurrence_date::text, sc.name, s.status, '
            . 'count(*) FILTER (WHERE a.removed_at IS NULL)::int, count(*) FILTER (WHERE a.removed_at IS NOT NULL)::int '
            . 'FROM meetings.meeting_session s JOIN meetings.meeting_schedule sc ON sc.id = s.schedule_id '
            . 'JOIN attendance.attendance a ON a.session_id = s.id GROUP BY 1, 2, 3 ORDER BY 1',
        ));

        self::assertSame([['Mina', 'Hanna', 'Youth Meeting', 'Marked in the wrong group']], $this->rows(
            'SELECT p.first_name, p.last_name, s.name, a.removal_reason FROM attendance.attendance a '
            . 'JOIN people.person p ON p.id = a.person_id '
            . 'JOIN people.membership m ON m.person_id = p.id AND m.stage_id IS NULL '
            . 'JOIN organization.service s ON s.id = m.service_id WHERE a.removed_at IS NOT NULL',
        ));
    }

    private function seed(): void
    {
        new CommandTester(new Application(self::bootKernel())->find('app:demo:seed'))->execute([]);
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

    public function testTheCopticCourseHasTwoPublishedTasksADraftAndTheGreetingReportedByBothMembers(): void
    {
        self::mockTime('2026-11-07T10:00:00+01:00');
        $this->seed();

        self::assertSame([
            ['Read five short words',           'draft',     null],
            ['Say the Easter greeting at home', 'published', 'hoda@example.org nevine.boulos@example.org'],
            ['Write the first eight letters',   'published', null],
        ], $this->rows(
            'SELECT h.title, h.status, (SELECT string_agg(u.email, \' \' ORDER BY u.email) FROM learning.homework_completion c '
            . 'JOIN identity.app_user u ON u.id = c.completed_by WHERE c.assignment_id = h.id AND c.undone_at IS NULL) '
            . "FROM learning.homework_assignment h JOIN organization.stage s ON s.id = h.stage_id WHERE s.name = 'Level 1' "
            . 'ORDER BY h.title',
        ));

        self::assertSame([[0]], $this->rows(
            'SELECT count(*)::int FROM (SELECT person_id FROM identity.app_user WHERE person_id IS NOT NULL '
            . 'GROUP BY person_id HAVING count(*) > 1) shared',
        ));
    }
}
