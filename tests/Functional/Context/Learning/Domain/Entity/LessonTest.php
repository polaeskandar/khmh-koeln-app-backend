<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Database;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class LessonTest extends KernelTestCase
{
    public function testAMeetingHasOneLessonUntilItIsArchived(): void
    {
        $schedule = Uuid::v7();
        $archived = $this->lesson($schedule);
        $archived->archive(new DateTimeImmutable('2026-11-01T12:00:00+01:00'));
        $this->database()->persisted($archived, $this->lesson($schedule));

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, fn () => $this->database()->persisted($this->lesson(
            $schedule,
        )));
    }

    public function testLessonsWithoutAScheduleNeverClash(): void
    {
        $this->database()->persisted($this->lesson(null), $this->lesson(null));

        self::assertSame(2, $this->database()->connection()->fetchOne('SELECT count(*) FROM learning.lesson'));
    }

    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $set): void
    {
        $this->database()->persisted($this->lesson(null));

        $this->database()->assertRefused(Database::CHECK_VIOLATION, 'UPDATE learning.lesson SET ' . $set);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function violations(): iterable
    {
        yield 'unknown status' => ["status = 'taught'"];
        yield 'completed without a time' => ["status = 'completed'"];
        yield 'a time without completing' => ['completed_at = created_at'];
        yield 'blank title' => ["title = '   '"];
        yield 'eleven materials' => ["materials = (SELECT jsonb_agg('{}'::jsonb) FROM generate_series(1, 11))"];
    }

    private function lesson(?Uuid $scheduleId): Lesson
    {
        return new Lesson(
            Uuid::v7(),
            new MeetingLink($scheduleId, LocalDate::of('2026-11-15')),
            new LessonText('The Good Samaritan'),
            responsibleId: null,
        );
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
