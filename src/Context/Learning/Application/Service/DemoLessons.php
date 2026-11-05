<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class DemoLessons
{
    private const string TOGETHER = 'Do it together at home and tell us on Sunday how it went.';

    public function __construct(
        private LessonRepositoryInterface $lessons,
        private HomeworkRepositoryInterface $homework,
        private HomeworkReportRepositoryInterface $reports,
        private Calendar $calendar,
    ) {
    }

    public function seed(Uuid $stageId, Uuid $scheduleId, Uuid $servantId, Uuid $parentId, Uuid $childId): void
    {
        $today = $this->calendar->today();
        $sunday = $today->addDays(-$today->isoWeekday());

        $taught = new Lesson(
            $stageId,
            new MeetingLink($scheduleId, $sunday),
            new LessonText('The Lost Sheep', passage: 'Luke 15:3–7'),
            $servantId,
        );

        $taught->changeStatus(LessonStatus::Completed, $today, $this->at($sunday, '13:15'));
        $this->lessons->save($taught);

        $next = new LessonText('Zacchaeus Meets Jesus', passage: 'Luke 19:1–10');
        $ready = new Lesson($stageId, new MeetingLink($scheduleId, $sunday->addDays(7)), $next, $servantId);
        $ready->changeStatus(LessonStatus::Ready, $today, $this->calendar->now());
        $this->lessons->save($ready);

        $story = $this->task($taught, 'Read the story together', $sunday->addDays(6), $servantId);
        $story->publish($this->at($sunday, '13:30'));
        $this->homework->save($story);
        $this->reports->reportDone($story->id(), $childId, $this->at($sunday, '19:00'), $parentId);
        $this->homework->save($this->task($ready, 'Learn the memory verse', dueDate: null, servantId: $servantId));
    }

    private function task(Lesson $lesson, string $title, ?LocalDate $dueDate, Uuid $servantId): HomeworkAssignment
    {
        return HomeworkAssignment::draft(
            $lesson,
            HomeworkText::of($title, self::TOGETHER, $dueDate),
            Materials::none(),
            $servantId,
        );
    }

    private function at(LocalDate $date, string $time): DateTimeImmutable
    {
        return $this->calendar->instantAt($date, LocalTime::of($time));
    }
}
