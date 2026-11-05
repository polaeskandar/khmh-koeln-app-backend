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
use App\Context\Learning\Domain\ValueObject\ResponsibleChoice;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonChoice;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\OfferedMeetings;
use App\Workflow\Learning\Model\WrittenLesson;
use Symfony\Component\Uid\Uuid;

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

    public function __invoke(string $id, LessonChoice $choice): WrittenLesson
    {
        $this->access->require(Action::Update, Resource::Lessons, refusal: 'lessons.changeStageOnly');

        $lesson = $this->lessons->editable($id);
        $stage = LessonStages::opened($this->structure->stage($lesson->stageId()->toRfc4122()));
        $draft = $choice->draft();

        return WrittenLesson::of($this->meetings->whileScheduleKept(
            $draft->meeting->scheduleWithoutSession(),
            fn (): Lesson => $this->lessons->edit(
                $lesson,
                $stage,
                $draft,
                $this->offeredFor($draft->meeting),
                $this->responsibleOf($choice->responsibleId),
            ),
        ));
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

    private function responsibleOf(?string $sent): ResponsibleChoice
    {
        $id = $sent !== null && Uuid::isValid($sent) ? Uuid::fromString($sent) : null;

        return new ResponsibleChoice(
            $id,
            $id !== null && $this->accounts->isActiveAndAllowedTo($id, Action::Update, Resource::Lessons),
        );
    }
}
