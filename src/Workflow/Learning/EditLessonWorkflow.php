<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\RoleHolders;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\LessonWrites;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\OfferedMeeting;
use App\Context\Learning\Domain\ValueObject\TeamChoice;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Scheduling\Application\Service\MeetingLookup;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Library\Problem\Model\Ids;
use App\Platform\Access\Model\Group;
use App\Workflow\Learning\Model\LessonChoice;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\OfferedMeetings;
use App\Workflow\Learning\Model\TeamChoices;
use App\Workflow\Learning\Model\WrittenLesson;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class EditLessonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private MeetingLookup $meetings,
        private AccountLookup $accounts,
        private LessonWrites $lessons,
        private RoleHolders $holders,
    ) {
    }

    public function __invoke(string $id, LessonChoice $choice, ?int $version): WrittenLesson
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.changeStageOnly');

        $lesson = $this->lessons->editable(
            Ids::toUuid($id, notFound: 'lesson'),
            $version,
            ensureAllowed: $this->ensureAllowed(...),
        );

        $group = StructureGroup::ofStage($this->structure->getStage($lesson->stageId()));
        $stage = LessonStages::opened($this->structure->getStage($lesson->stageId()));
        $draft = $choice->draft();

        return WrittenLesson::of($this->meetings->whileScheduleKept(
            $draft->meeting->scheduleWithoutSession(),
            fn (): Lesson => $this->lessons->edit(
                $lesson,
                $stage,
                $draft,
                $this->offeredFor($draft->meeting),
                $this->teamOf($choice, $group),
            ),
        ));
    }

    private function ensureAllowed(Lesson $lesson): void
    {
        $stage = $this->structure->getStage($lesson->stageId());
        $this->access->requireIn(Action::Update, Resource::Lessons, StructureGroup::ofStage($stage), missing: 'lesson');
    }

    private function offeredFor(MeetingChoice $choice): ?OfferedMeeting
    {
        return OfferedMeetings::of(match (true) {
            $choice->sessionId !== null => $this->meetings->findSlotOfSession($choice->session()),
            $choice->scheduleId !== null => $this->meetings->findSlotOfSchedule(
                $choice->schedule(),
                $choice->day(),
                ArchivedStructure::of($this->structure->archivedIds()),
            ),
            default => null,
        });
    }

    private function teamOf(LessonChoice $choice, Group $group): TeamChoice
    {
        return TeamChoices::of(
            $choice,
            $this->accounts->enabledAmong($this->holders->thatCanIn(Action::Update, Resource::Lessons, $group)),
        );
    }
}
