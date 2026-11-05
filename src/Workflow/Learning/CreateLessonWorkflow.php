<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonWrites;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonChoice;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\OfferedMeetings;
use App\Workflow\Learning\Model\ResponsibleChoices;
use App\Workflow\Learning\Model\WrittenLesson;

final readonly class CreateLessonWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private MeetingLookup $meetings,
        private AccountLookup $accounts,
        private LessonWrites $lessons,
    ) {
    }

    public function __invoke(string $stageId, LessonChoice $choice): WrittenLesson
    {
        $this->access->require(Action::Create, Resource::Lessons, refusal: 'lessons.prepareStageOnly');

        $stage = LessonStages::opened($this->structure->stage($stageId));
        $draft = $choice->draft();

        return WrittenLesson::of($this->lessons->create(
            $stage,
            $draft,
            OfferedMeetings::lookedUpIn($this->meetings, $draft->meeting),
            ResponsibleChoices::lookedUpIn($this->accounts, $choice->responsibleId),
        ));
    }
}
