<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Scheduling\Domain\Entity;

use App\Tests\Support\Context\Scheduling\Domain\Entity\Meetings;
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

    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $set): void
    {
        $schedule = Meetings::schedule();
        $this->database()->persisted($schedule, Meetings::session($schedule));

        $this->database()->assertRefused(Database::CHECK_VIOLATION, 'UPDATE scheduling.session SET ' . $set);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function violations(): iterable
    {
        yield 'unknown status' => ["status = 'skipped'"];
        yield 'reason without cancellation' => ["cancel_reason = 'Rain'"];
        yield 'completed without closing' => ["status = 'completed', opened_at = created_at"];
        yield 'closed but still active' => ["status = 'active', opened_at = created_at, closed_at = created_at"];
        yield 'active without opening' => ["status = 'active'"];
        yield 'blank title' => ["title = ' '"];
        yield 'unknown roster mode' => ["roster_mode = 'closed'"];
        yield 'end at start' => ['end_time = start_time'];
    }

    public function testACompletedSessionAndACanceledOneWithAReasonAreAccepted(): void
    {
        $schedule = Meetings::schedule();

        $this->database()->persisted(
            $schedule,
            Meetings::session($schedule),
            Meetings::session($schedule, '2026-11-15'),
        );

        $connection = $this->database()->connection();

        $connection->executeStatement(
            "UPDATE scheduling.session SET status = 'completed', opened_at = created_at, closed_at = created_at WHERE occurrence_date = '2026-11-08'",
        );

        $connection->executeStatement(
            "UPDATE scheduling.session SET status = 'canceled', cancel_reason = 'Holiday' WHERE occurrence_date = '2026-11-15'",
        );

        self::assertSame(
            2,
            $connection->fetchOne("SELECT count(*) FROM scheduling.session WHERE status IN ('completed', 'canceled')"),
        );
    }

    public function testAScheduleWithASessionCannotBeDeleted(): void
    {
        $schedule = Meetings::schedule();
        $this->database()->persisted($schedule, Meetings::session($schedule));

        $this->database()->assertRefused(Database::RESTRICT_VIOLATION, 'DELETE FROM scheduling.schedule');
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
