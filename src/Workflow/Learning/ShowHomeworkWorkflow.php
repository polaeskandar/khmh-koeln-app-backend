<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\HomeworkReading;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\StageRosters;
use App\Workflow\Learning\Model\AudienceCounts;
use App\Workflow\Learning\Model\AudienceSize;
use App\Workflow\Learning\Model\HomeworkAbilities;
use App\Workflow\Learning\Model\HomeworkDetail;
use App\Workflow\Learning\Model\HomeworkFacts;
use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Learning\Model\HomeworkView;
use App\Workflow\Learning\Model\LessonStageView;
use App\Workflow\Learning\Model\TaskAudiences;

final readonly class ShowHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private HomeworkReports $homework,
        private StructureLookup $structure,
        private StageRosters $rosters,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $id): HomeworkDetail
    {
        $this->access->require(Action::Read, Resource::Homework);

        $reading = $this->homework->reading($id);
        $task = $reading->task;
        $stage = $this->structure->stage($task->stageId()->toRfc4122());

        return new HomeworkDetail(
            task: HomeworkView::of($reading),
            lesson: HomeworkLessonView::of($task->lesson()),
            stage: LessonStageView::of($stage),
            facts: $this->factsOf($reading, $stage),
            can: HomeworkAbilities::grantedBy($this->access, $task, $stage),
        );
    }

    private function factsOf(HomeworkReading $reading, Stage $stage): HomeworkFacts
    {
        $today = $reading->reports->today;

        return new HomeworkFacts(
            today: $today->toString(),
            minDueDate: $reading->task->minDueDate()->toString(),
            audienceSize: AudienceSize::of($this->rosters, $stage, $today),
            counts: $this->countsOf($reading),
        );
    }

    private function countsOf(HomeworkReading $reading): ?AudienceCounts
    {
        if ($reading->task->state()->status === HomeworkStatus::Draft) {
            return null;
        }

        $audiences = TaskAudiences::lookedUpIn($this->structure, $this->rosters, $this->accounts, $reading->reports);

        return $audiences->countsOf($reading->task);
    }
}
