<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonTab;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonCollaboratorRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkCounts;
use App\Context\Learning\Domain\ValueObject\LessonListing;
use App\Context\Learning\Domain\ValueObject\LessonReading;
use App\Context\Learning\Domain\ValueObject\LessonTabs;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class StageLessons
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private HomeworkRepositoryInterface $homework,
        private LessonCollaboratorRepositoryInterface $collaborators,
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

    public function listed(Uuid $stageId, LessonTab $tab, ?string $query): LessonListing
    {
        $today = $this->calendar->today();
        $tabs = new LessonTabs($this->lessons->ofStage($stageId), $today);
        $shown = $tabs->shown($tab, $query);
        $ids = new ArrayCollection($shown)->map(static fn (Lesson $lesson): Uuid => $lesson->id())->getValues();

        return new LessonListing(
            $today,
            $tabs->counts(),
            $shown,
            $this->homework->countsByLesson($ids),
            $this->collaborators->ofLessons($ids),
        );
    }

    public function reading(Uuid $id): LessonReading
    {
        $lesson = $this->lessons->find($id) ?? throw NotFound::of('lesson');

        $counts = $this->homework->countsByLesson([$lesson->id()])->get($lesson->id()->toRfc4122());

        return new LessonReading(
            $lesson,
            $counts ?? new HomeworkCounts(),
            $this->calendar->today(),
            $this->collaborators->ofLessons([$lesson->id()]),
        );
    }
}
