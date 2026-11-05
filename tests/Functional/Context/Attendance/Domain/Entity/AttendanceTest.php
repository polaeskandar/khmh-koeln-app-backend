<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Attendance\Domain\Entity;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Tests\Support\Database;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AttendanceTest extends KernelTestCase
{
    public function testAPersonHasOneCurrentMarkPerSession(): void
    {
        [$session, $person] = [Uuid::v7(), Uuid::v7()];
        $removed = Attendance::record($session, $person, new DateTimeImmutable('2026-11-08T12:00:00+01:00'), null);
        $removed->remove(null, new DateTimeImmutable('2026-11-08T12:01:00+01:00'), 'Wrong child');

        $this->database()->persisted($removed, Attendance::record(
            $session,
            $person,
            new DateTimeImmutable('2026-11-08T12:02:00+01:00'),
            null,
        ));

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, fn () => $this->database()->persisted(Attendance::record(
            $session,
            $person,
            new DateTimeImmutable('2026-11-08T12:03:00+01:00'),
            null,
        )));
    }

    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $set): void
    {
        $this->database()->persisted(Attendance::record(
            Uuid::v7(),
            Uuid::v7(),
            new DateTimeImmutable('2026-11-08T12:00:00+01:00'),
            null,
        ));

        $this->database()->assertRefused(Database::CHECK_VIOLATION, 'UPDATE attendance.attendance SET ' . $set);
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

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
