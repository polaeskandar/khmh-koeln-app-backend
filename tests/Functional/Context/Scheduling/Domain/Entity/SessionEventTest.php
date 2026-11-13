<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Scheduling\Domain\Entity;

use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Entity\SessionEvent;
use App\Context\Scheduling\Domain\Enum\SessionEventType;
use App\Context\Scheduling\Domain\ValueObject\Actor;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Meetings;
use App\Tests\Support\Database;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class SessionEventTest extends KernelTestCase
{
    public function testTheHistoryKeepsWhoDidWhatAndWhen(): void
    {
        $session = $this->storedSession();
        $at = new DateTimeImmutable('2026-11-07T18:00:00+01:00');

        $this->database()->persisted(
            new SessionEvent($session, SessionEventType::Created, $at, Actor::generator(), note: null),
            new SessionEvent($session, SessionEventType::Canceled, $at, Actor::account(Uuid::v7()), note: 'Rain'),
        );

        self::assertSame(2, $this->database()->connection()->fetchOne('SELECT count(*) FROM scheduling.session_event'));
    }

    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $set): void
    {
        $session = $this->storedSession();
        $at = new DateTimeImmutable('2026-11-07T18:00:00+01:00');

        $this->database()->persisted(
            new SessionEvent($session, SessionEventType::Created, $at, Actor::generator(), note: null),
        );

        $this->database()->assertRefused(Database::CHECK_VIOLATION, 'UPDATE scheduling.session_event SET ' . $set);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function violations(): iterable
    {
        yield 'unknown type' => ["type = 'moved'"];
        yield 'generator with an actor' => ["actor_id = '019a3b6e-0000-7000-8000-000000000009'"];
        yield 'generator opening a session' => ["type = 'opened'"];
    }

    public function testASessionWithHistoryCannotBeDeleted(): void
    {
        $session = $this->storedSession();

        $this->database()->persisted(
            new SessionEvent(
                $session,
                SessionEventType::Created,
                new DateTimeImmutable(),
                Actor::generator(),
                note: null,
            ),
        );

        $this->database()->assertRefused(Database::RESTRICT_VIOLATION, 'DELETE FROM scheduling.session');
    }

    private function storedSession(): Session
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
