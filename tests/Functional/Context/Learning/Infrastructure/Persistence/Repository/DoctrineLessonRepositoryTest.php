<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineLessonRepositoryTest extends KernelTestCase
{
    public function testTheMeetingIndexRaceIsTheTakenMeetingFieldError(): void
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);
        $class = new Schedules(self::getContainer())->weekly($service, 'Sunday class', 7, stage: $third);
        new Lessons(self::getContainer())->stored($third, $class, '2026-11-15');
        $lessons = self::getContainer()->get(LessonRepositoryInterface::class);
        self::assertInstanceOf(LessonRepositoryInterface::class, $lessons);

        $late = new Lesson(
            $third->id(),
            new MeetingLink($class->id(), LocalDate::of('2026-11-15')),
            new LessonText('Late'),
            responsibleId: null,
        );

        try {
            $lessons->save($late);
            self::fail('The second lesson of the meeting was stored.');
        } catch (InvalidInput $refused) {
            self::assertSame(
                ['meeting' => ['code' => 'lessons.meetingHasLesson', 'params' => ['title' => 'The Good Samaritan']]],
                $refused->fieldErrors,
            );
        }
    }
}
