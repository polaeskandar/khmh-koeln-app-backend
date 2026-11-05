<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Learning\Model\LessonAbilities;
use App\Workflow\Learning\Model\LessonList;
use App\Workflow\Learning\Model\LessonRows;
use App\Workflow\Learning\Model\LessonStageView;

final readonly class ListLessonsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private StageLessons $lessons,
        private MeetingLookup $meetings,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $stageId, string $tab, ?string $query): LessonList
    {
        $this->access->require(Action::Read, Resource::Lessons);

        $stage = $this->structure->stage($stageId);
        $listing = $this->lessons->listed($stage->id(), $tab, $query);

        return new LessonList(
            stage: LessonStageView::of($stage),
            today: $listing->today->toString(),
            tabs: $listing->tabs,
            abilities: LessonAbilities::grantedBy($this->access, $this->accounts, $stage),
            items: LessonRows::lookedUpIn($this->meetings, $this->accounts, $listing),
        );
    }
}
