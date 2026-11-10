<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonWrites;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\OfferedMeeting;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Model\Ids;
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

        $group = StructureGroup::ofStage($this->structure->stage($lesson->stageId()));
        $stage = LessonStages::opened($this->structure->stage($lesson->stageId()));
        $draft = $choice->draft();

        return WrittenLesson::of($this->meetings->whileScheduleKept(
            $draft->meeting->scheduleWithoutSession(),
            fn (): Lesson => $this->lessons->edit(
                $lesson,
                $stage,
                $draft,
                $this->offeredFor($draft->meeting),
                TeamChoices::of($choice, $this->accounts->activeAllowedIn(Action::Update, Resource::Lessons, $group)),
            ),
        ));
    }

    private function ensureAllowed(Lesson $lesson): void
    {
        $stage = $this->structure->stage($lesson->stageId());
        $this->access->requireIn(Action::Update, Resource::Lessons, StructureGroup::ofStage($stage), missing: 'lesson');
    }

    private function offeredFor(MeetingChoice $choice): ?OfferedMeeting
    {
        return OfferedMeetings::of(match (true) {
            $choice->sessionId !== null => $this->meetings->slotOfSession($choice->session()),
            $choice->scheduleId !== null => $this->meetings->slotOfSchedule(
                $choice->schedule(),
                $choice->day(),
                ArchivedStructure::of($this->structure->archivedIds()),
            ),
            default => null,
        });
    }
}
