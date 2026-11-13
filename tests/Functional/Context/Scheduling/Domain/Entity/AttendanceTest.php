<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Scheduling\Domain\Entity;

use App\Context\Scheduling\Domain\Entity\Attendance;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\ValueObject\Removal;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Meetings;
use App\Tests\Support\Database;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AttendanceTest extends KernelTestCase
{
    public function testAPersonHasOneCurrentMarkPerSession(): void
    {
        [$session, $person] = [$this->session(), Uuid::v7()];
        $removed = new Attendance($session, $person, new DateTimeImmutable('2026-11-08T12:00:00+01:00'), null);
        $removed->remove(Removal::of(new DateTimeImmutable('2026-11-08T12:01:00+01:00'), null, 'Wrong child'));

        $this->database()->persisted(
            $removed,
            new Attendance($session, $person, new DateTimeImmutable('2026-11-08T12:02:00+01:00'), null),
        );

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, fn () => $this->database()->persisted(
            new Attendance($session, $person, new DateTimeImmutable('2026-11-08T12:03:00+01:00'), null),
        ));
    }

    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $set): void
    {
        $this->database()->persisted(
            new Attendance($this->session(), Uuid::v7(), new DateTimeImmutable('2026-11-08T12:00:00+01:00'), null),
        );

        $this->database()->assertRefused(Database::CHECK_VIOLATION, 'UPDATE scheduling.attendance SET ' . $set);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function violations(): iterable
    {
        yield 'remover without removal' => ["removed_by = '019a3b6e-0000-7000-8000-000000000009'"];
        yield 'reason without removal' => ["removal_reason = 'Wrong child'"];
        yield 'removed before recorded' => ["removed_at = recorded_at - interval '1 minute'"];
    }

    public function testASessionWithMarksCanNotBeDeleted(): void
    {
        $session = $this->session();

        $this->database()->persisted(
            new Attendance($session, Uuid::v7(), new DateTimeImmutable('2026-11-08T12:00:00+01:00'), null),
        );

        $this->database()->assertRefused(
            Database::RESTRICT_VIOLATION,
            "DELETE FROM scheduling.session WHERE id = '" . $session->id()->toRfc4122() . "'",
        );
    }

    private function session(): Session
    {
        $schedule = Meetings::schedule();
        $session = Meetings::session($schedule);
        $this->database()->persisted($schedule, $session);

        return $session;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
