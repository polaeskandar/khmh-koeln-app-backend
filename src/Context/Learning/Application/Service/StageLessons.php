<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonTab;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonHomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkTally;
use App\Context\Learning\Domain\ValueObject\LessonListing;
use App\Context\Learning\Domain\ValueObject\LessonReading;
use App\Context\Learning\Domain\ValueObject\LessonTabs;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class StageLessons
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private HomeworkRepositoryInterface $homework,
        private LessonHomeworkRepositoryInterface $lessonHomework,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<Uuid> $stageIds
     */
    public function countIn(array $stageIds): int
    {
        return $this->lessons->countForStages($stageIds);
    }

    public function hasPublishedHomework(Lesson $lesson): bool
    {
        return $this->lessonHomework->hasPublished($lesson->id());
    }

    public function listed(Uuid $stageId, string $tab, ?string $query): LessonListing
    {
        $chosen = LessonTab::tryFrom($tab) ?? throw InvalidInput::field('tab', 'validation.invalidChoice');
        $today = $this->calendar->today();
        $tabs = new LessonTabs($this->lessons->ofStage($stageId), $today);
        $shown = $tabs->shown($chosen, $query);

        return new LessonListing($today, $tabs->counts(), $shown, $this->homeworkOf($shown));
    }

    public function reading(string $id): LessonReading
    {
        $lesson = $this->lessons->find(Uuid::fromString($id)) ?? throw NotFound::of('lesson');
        $state = $lesson->state();

        return new LessonReading(
            $lesson,
            $this->homeworkOf([$lesson]),
            $this->calendar->today(),
            completedAt: $this->instantOf($state->completedAt),
            archivedAt: $this->instantOf($state->archivedAt),
        );
    }

    /**
     * @param list<Lesson> $lessons
     */
    private function homeworkOf(array $lessons): HomeworkTally
    {
        $ids = new ArrayCollection($lessons)->map(static fn (Lesson $lesson): Uuid => $lesson->id())->getValues();

        return new HomeworkTally($this->homework->countsByLesson($ids));
    }

    private function instantOf(?DateTimeInterface $instant): ?string
    {
        return $instant === null ? null : $this->calendar->formatInstant($instant);
    }
}
