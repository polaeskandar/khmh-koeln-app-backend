<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Meetings\Domain\Entity;

use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Enum\SessionEventType;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\EventNote;
use App\Tests\Support\Context\Meetings\Domain\Entity\Meetings;
use App\Tests\Support\Database;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class SessionEventTest extends KernelTestCase
{
    public function testTheHistoryKeepsWhoDidWhatAndWhen(): void
    {
        $session = Meetings::oneOff();
        $at = new DateTimeImmutable('2026-11-07T18:00:00+01:00');

        $this->database()->persisted(
            $session,
            new SessionEvent($session, SessionEventType::Created, $at, Actor::generator(), EventNote::none()),
            new SessionEvent(
                $session,
                SessionEventType::Moved,
                $at,
                Actor::account(Uuid::v7()),
                EventNote::movedFrom($at),
            ),
            new SessionEvent(
                $session,
                SessionEventType::Canceled,
                $at,
                Actor::account(Uuid::v7()),
                EventNote::reason('Rain'),
            ),
        );

        self::assertSame(3, $this->database()->connection()->fetchOne('SELECT count(*) FROM meetings.session_event'));
    }

    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $set): void
    {
        $session = Meetings::oneOff();
        $at = new DateTimeImmutable('2026-11-07T18:00:00+01:00');

        $this->database()->persisted(
            $session,
            new SessionEvent($session, SessionEventType::Created, $at, Actor::generator(), EventNote::none()),
        );

        $this->database()->assertRefused(Database::CHECK_VIOLATION, 'UPDATE meetings.session_event SET ' . $set);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function violations(): iterable
    {
        yield 'unknown type' => ["type = 'skipped'"];
        yield 'generator with an actor' => ["actor_id = '019a3b6e-0000-7000-8000-000000000009'"];
        yield 'generator opening a session' => ["type = 'opened'"];
        yield 'move without the previous start' => ["type = 'moved', by_generator = false"];
        yield 'previous start without a move' => ['previous_starts_at = occurred_at'];
    }

    public function testASessionWithHistoryCannotBeDeleted(): void
    {
        $session = Meetings::oneOff();

        $this->database()->persisted(
            $session,
            new SessionEvent(
                $session,
                SessionEventType::Created,
                new DateTimeImmutable(),
                Actor::generator(),
                EventNote::none(),
            ),
        );

        $this->database()->assertRefused(Database::RESTRICT_VIOLATION, 'DELETE FROM meetings.meeting_session');
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
