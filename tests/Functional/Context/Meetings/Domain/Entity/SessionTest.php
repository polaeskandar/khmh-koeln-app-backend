<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Meetings\Domain\Entity;

use App\Tests\Support\Context\Meetings\Domain\Entity\Meetings;
use App\Tests\Support\Database;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SessionTest extends KernelTestCase
{
    public function testAScheduleHasOneSessionPerOccurrence(): void
    {
        $schedule = Meetings::schedule();
        $this->database()->persisted($schedule, Meetings::session($schedule));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->database()->persisted(Meetings::session($schedule));
    }

    public function testTwoOneOffsOnTheSameDateCoexist(): void
    {
        $this->database()->persisted(Meetings::oneOff(), Meetings::oneOff());

        self::assertSame(2, $this->database()->connection()->fetchOne('SELECT count(*) FROM meetings.meeting_session'));
    }

    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $set): void
    {
        $this->database()->persisted(Meetings::oneOff());

        $this->database()->assertRefused(Database::CHECK_VIOLATION, 'UPDATE meetings.meeting_session SET ' . $set);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function violations(): iterable
    {
        yield 'unknown status' => ["status = 'skipped'"];
        yield 'unknown roster mode' => ["roster_mode = 'closed'"];
        yield 'blank title' => ["title = ''"];
        yield 'ends when it starts' => ['ends_at = starts_at'];
        yield 'reason without cancellation' => ["cancel_reason = 'Rain'"];
        yield 'completed without closing' => ["status = 'completed', opened_at = starts_at"];
        yield 'closed but still active' => ["status = 'active', opened_at = starts_at, closed_at = ends_at"];
        yield 'active without opening' => ["status = 'active'"];
    }

    public function testACompletedSessionWithItsTimesAndACanceledOneWithAReasonAreAccepted(): void
    {
        $this->database()->persisted(Meetings::oneOff(), Meetings::oneOff('2026-11-15'));
        $connection = $this->database()->connection();

        $connection->executeStatement(
            "UPDATE meetings.meeting_session SET status = 'completed', opened_at = starts_at, closed_at = ends_at WHERE occurrence_date = '2026-11-08'",
        );

        $connection->executeStatement(
            "UPDATE meetings.meeting_session SET status = 'canceled', cancel_reason = 'Holiday' WHERE occurrence_date = '2026-11-15'",
        );

        self::assertSame(
            2,
            $connection->fetchOne(
                "SELECT count(*) FROM meetings.meeting_session WHERE status IN ('completed', 'canceled')",
            ),
        );
    }

    public function testAScheduleWithASessionCannotBeDeleted(): void
    {
        $schedule = Meetings::schedule();
        $this->database()->persisted($schedule, Meetings::session($schedule));

        $this->database()->assertRefused(Database::RESTRICT_VIOLATION, 'DELETE FROM meetings.meeting_schedule');
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
