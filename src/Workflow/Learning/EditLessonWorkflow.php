<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonWrites;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonChoice;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\OfferedMeetings;
use App\Workflow\Learning\Model\TeamChoices;
use App\Workflow\Learning\Model\WrittenLesson;

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
            $draft->meeting?->scheduleWithoutSession(),
            fn (): Lesson => $this->lessons->edit(
                $lesson,
                $stage,
                $draft,
                OfferedMeetings::lookedUpIn($this->meetings, $draft->meeting),
                TeamChoices::lookedUpIn($this->accounts, $choice),
            ),
        ));
    }
}
