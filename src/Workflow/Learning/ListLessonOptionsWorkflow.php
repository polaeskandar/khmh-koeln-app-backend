<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Query\LessonQueryInterface;
use App\Context\Learning\Domain\ValueObject\LessonWindow;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Learning\Model\LessonOptions;
use App\Workflow\Organization\Model\StructureGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ListLessonOptionsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private MeetingLookup $meetings,
        private LessonQueryInterface $lessons,
        private AccountLookup $accounts,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $stageId, ?string $lessonId): LessonOptions
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.prepareStageOnly');

        $stage = $this->structure->getStage(Ids::toUuid($stageId, notFound: 'stage'));
        $group = StructureGroup::ofStage($stage);
        $this->access->requireIn(Action::Update, Resource::Lessons, $group, missing: 'stage');

        $edited = $lessonId === null ? null : $this->editedIn($stage->id(), Ids::toUuid($lessonId, notFound: 'lesson'));

        $window = LessonWindow::around($this->calendar->today(), $edited);
        $archived = ArchivedStructure::of($this->structure->archivedIds());
        $dates = $this->meetings->listDatesOfStage($stage->id(), $archived, $window->from, $window->to);

        return LessonOptions::of(
            $window,
            $dates,
            $this->takenIn(self::scheduleIdsOf($dates), $window, $edited),
            $this->accounts->listActiveAllowedIn(Action::Update, Resource::Lessons, $group),
        );
    }

    private function editedIn(Uuid $stageId, Uuid $lessonId): Lesson
    {
        $lesson = $this->lessons->find($lessonId);

        return $lesson?->stageId()->equals($stageId) === true ? $lesson : throw NotFound::of('lesson');
    }

    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return ReadableCollection<int, Lesson>
     */
    private function takenIn(array $scheduleIds, LessonWindow $window, ?Lesson $edited): ReadableCollection
    {
        return $this->lessons
            ->listCurrentBetween($scheduleIds, $window->from, $window->to)
            ->filter(static fn (Lesson $lesson): bool => $lesson !== $edited);
    }

    /**
     * @param list<PlannedDate> $dates
     *
     * @return list<Uuid>
     */
    private static function scheduleIdsOf(array $dates): array
    {
        return new ArrayCollection($dates)->map(
            static fn (PlannedDate $date): Uuid => $date->schedule->id(),
        )->getValues();
    }
}
