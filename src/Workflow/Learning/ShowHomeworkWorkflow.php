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
use App\Context\People\Domain\ValueObject\RosterBook;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Workflow\Learning\Model\AudienceAccounts;
use App\Workflow\Learning\Model\AudienceCounts;
use App\Workflow\Learning\Model\AudienceKeys;
use App\Workflow\Learning\Model\AudienceSize;
use App\Workflow\Learning\Model\HomeworkDetail;
use App\Workflow\Learning\Model\HomeworkFacts;
use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Learning\Model\HomeworkView;
use App\Workflow\Learning\Model\LessonStageView;
use App\Workflow\Learning\Model\TaskAudiences;
use App\Workflow\Organization\Model\StructureGroup;
use Doctrine\Common\Collections\ArrayCollection;

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

        $this->access->requireIn(
            Action::Read,
            Resource::Homework,
            StructureGroup::ofStage($stage),
            missing: 'homework',
        );

        return new HomeworkDetail(
            task: HomeworkView::of($reading),
            lesson: HomeworkLessonView::of($task->lesson()),
            stage: LessonStageView::of($stage),
            facts: $this->factsOf($reading, $stage),
        );
    }

    private function factsOf(HomeworkReading $reading, Stage $stage): HomeworkFacts
    {
        $task = $reading->task;
        $reports = $reading->reports;
        $key = AudienceKeys::keyOf($stage, $reports->today);
        $book = $this->rosters->on([$key], $reports->reporterIds());

        $counts = $task->state()->status === HomeworkStatus::Draft ? null : $this->countsOf($reading, $book, $key);

        return new HomeworkFacts(
            today: $reports->today->toString(),
            minDueDate: $task->text()->earliestDueDate($task->lesson()->link()->teachingDate)->toString(),
            audienceSize: AudienceSize::of($book, $key),
            counts: $counts,
            lessonArchived: $task->lesson()->state()->archivedAt !== null,
        );
    }

    private function countsOf(HomeworkReading $reading, RosterBook $book, RosterKey $key): AudienceCounts
    {
        $reports = $reading->reports;
        $keys = new ArrayCollection([$reading->task->stageId()->toRfc4122() => $key]);

        $accounts = AudienceAccounts::of(
            $book,
            $this->accounts->accounts(TaskAudiences::accountIdsOf($book, $reports)),
            $this->accounts->ownAccountsOf($book->personIds()),
        );

        return TaskAudiences::of($reports, $keys, $book, $accounts)->countsOf($reading->task);
    }
}
