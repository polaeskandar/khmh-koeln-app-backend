<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\Learning\Domain\ValueObject\StageHomework;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\StageRosters;
use App\Workflow\Learning\Model\AudienceSize;
use App\Workflow\Learning\Model\HomeworkItems;
use App\Workflow\Learning\Model\HomeworkLessons;
use App\Workflow\Learning\Model\HomeworkList;
use App\Workflow\Learning\Model\LessonStages;
use App\Workflow\Learning\Model\StageHomeworkFacts;
use App\Workflow\Learning\Model\TaskAudiences;
use App\Workflow\Organization\Model\StageSummary;

final readonly class ListHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private HomeworkReports $homework,
        private StageRosters $rosters,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $stageId): HomeworkList
    {
        $this->access->require(Action::Read, Resource::Homework);

        $stage = $this->structure->stage($stageId);
        $homework = $this->homework->ofStage($stage->id());
        $audiences = TaskAudiences::lookedUpIn($this->structure, $this->rosters, $this->accounts, $homework->reports);

        return new HomeworkList(
            stage: StageSummary::of($stage),
            facts: $this->factsOf($homework, $stage),
            lessons: HomeworkLessons::of($homework->lessons, $homework->reports->today),
            items: HomeworkItems::of($audiences, $this->access, $stage),
        );
    }

    private function factsOf(StageHomework $homework, Stage $stage): StageHomeworkFacts
    {
        $today = $homework->reports->today;
        $canCreate = LessonStages::isOpen($stage) && $this->access->allows(Action::Create, Resource::Homework);

        return new StageHomeworkFacts(
            today: $today->toString(),
            tabs: $homework->tabs(),
            audienceSize: AudienceSize::of($this->rosters, $stage, $today),
            canCreate: $canCreate,
        );
    }
}
