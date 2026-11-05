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

    private const string PRACTISE = 'Practise at home and bring it to the next class.';

    private const array ALPHABET = [
        ['label' => 'The Coptic alphabet (chart)', 'url' => 'https://example.org/coptic-alphabet.pdf'],
    ];

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

    /**
     * @param list<array{Uuid, Uuid}> $selfReports the account and its own member record, for the Easter greeting
     */
    public function seedCourse(Uuid $stageId, Uuid $scheduleId, Uuid $teacherId, array $selfReports): void
    {
        $today = $this->calendar->today();
        $lastMonday = $today->addDays(-((($today->isoWeekday() + 5) % 7) + 1));

        $lessonIn = static fn (int $weeks, string $title): Lesson => new Lesson(
            $stageId,
            new MeetingLink($scheduleId, $lastMonday->addDays(7 * $weeks)),
            new LessonText($title),
            $teacherId,
        );

        $easter = $this->taught($lessonIn(-1, title: 'The Easter greeting'));
        $letters = $this->taught($lessonIn(0, title: 'The first eight letters'));
        $words = $lessonIn(1, title: 'Reading short words');
        $words->changeStatus(LessonStatus::Ready, $today, $this->calendar->now());
        $this->lessons->save($words);
        $this->courseHomework($easter, $letters, $words, $selfReports);
    }

    /**
     * @param list<array{Uuid, Uuid}> $selfReports
     */
    private function courseHomework(Lesson $easter, Lesson $letters, Lesson $words, array $selfReports): void
    {
        $greeting = $this->published($easter, 'Say the Easter greeting at home', Materials::none(), dueDate: null);
        $alphabet = Materials::of(self::ALPHABET, 'resources');
        $this->published($letters, 'Write the first eight letters', $alphabet, $words->link()->teachingDate);
        $draft = HomeworkText::of('Read five short words', self::PRACTISE, dueDate: null);
        $this->homework->save(HomeworkAssignment::draft($words, $draft, Materials::none(), $words->responsibleId()));
        $reportedAt = $this->at($easter->link()->teachingDate->addDays(2), '18:00');

        foreach ($selfReports as [$accountId, $personId]) {
            $this->reports->reportDone($greeting->id(), $personId, $reportedAt, $accountId);
        }
    }

    private function taught(Lesson $lesson): Lesson
    {
        $endedAt = $this->at($lesson->link()->teachingDate, '19:00');
        $lesson->changeStatus(LessonStatus::Completed, $this->calendar->today(), $endedAt);
        $this->lessons->save($lesson);

        return $lesson;
    }

    private function published(
        Lesson $lesson,
        string $title,
        Materials $resources,
        ?LocalDate $dueDate,
    ): HomeworkAssignment {
        $text = HomeworkText::of($title, self::PRACTISE, $dueDate);
        $task = HomeworkAssignment::draft($lesson, $text, $resources, $lesson->responsibleId());
        $task->publish($this->at($lesson->link()->teachingDate, '19:15'));
        $this->homework->save($task);

        return $task;
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
