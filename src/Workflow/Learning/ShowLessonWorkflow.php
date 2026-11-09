<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonReading;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Identity\Model\AccountReferences;
use App\Workflow\Learning\Model\LessonDetail;
use App\Workflow\Learning\Model\LessonDetailView;
use App\Workflow\Learning\Model\LessonHomework;
use App\Workflow\Learning\Model\LessonPlanView;
use App\Workflow\Learning\Model\LessonRow;
use App\Workflow\Learning\Model\LessonRows;
use App\Workflow\Learning\Model\LessonStageView;
use App\Workflow\Learning\Model\LessonSummary;
use App\Workflow\Learning\Model\MeetingViews;
use App\Workflow\Learning\Model\NoMeeting;
use App\Workflow\Learning\Model\OffScheduleMeeting;
use App\Workflow\Learning\Model\PlannedMeeting;
use App\Workflow\Learning\Model\SessionMeeting;
use Doctrine\Common\Collections\ArrayCollection;
use LogicException;

final readonly class ShowLessonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StageLessons $lessons,
        private StructureLookup $structure,
        private MeetingLookup $meetings,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $id): LessonDetail
    {
        $this->access->require(Action::Read, Resource::Lessons);

        $reading = $this->lessons->reading($id);
        $lesson = $reading->lesson;
        $stage = $this->structure->stage($lesson->stageId()->toRfc4122());
        $plan = LessonPlanView::of($lesson);

        return new LessonDetail(
            stage: LessonStageView::of($stage),
            today: $reading->today->toString(),
            lesson: LessonDetailView::of(
                $this->rowOf($reading),
                $lesson,
                $this->access->allows(Action::Read, Resource::Members) ? $plan : $plan->withoutNotes(),
            ),
        );
    }

    private function rowOf(LessonReading $reading): LessonRow
    {
        $lesson = $reading->lesson;
        $lessons = new ArrayCollection([$lesson]);

        $accounts = AccountReferences::of($this->accounts->accounts(LessonRows::accountIdsOf(
            $lessons,
            $reading->helpers,
        )));

        return new LessonRow(
            LessonSummary::of($lesson),
            $accounts->referenceOf($lesson->responsibleId()),
            $accounts->byNameOf($reading->helpers->of($lesson)),
            $this->meetingOf($lesson),
            LessonHomework::of($reading->homework),
        );
    }

    private function meetingOf(Lesson $lesson): NoMeeting|OffScheduleMeeting|PlannedMeeting|SessionMeeting
    {
        $lessons = new ArrayCollection([$lesson]);
        $schedules = $this->meetings->schedules(MeetingViews::scheduleIdsOf($lessons));

        $views = MeetingViews::of(
            $schedules,
            $this->meetings->sessionsOn($schedules, MeetingViews::datesOf($lessons)),
            $lessons,
            ArchivedStructure::of($this->structure->archivedIds()),
        );

        return $views[0] ?? throw new LogicException('The lesson needs its meeting view.');
    }
}
