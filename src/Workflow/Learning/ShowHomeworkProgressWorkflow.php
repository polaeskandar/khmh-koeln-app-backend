<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\Learning\Domain\ValueObject\HomeworkReading;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\StageRosters;
use App\Workflow\Learning\Model\AlphabeticalRows;
use App\Workflow\Learning\Model\AudienceAccounts;
use App\Workflow\Learning\Model\AudienceKeys;
use App\Workflow\Learning\Model\HomeworkItems;
use App\Workflow\Learning\Model\HomeworkProgress;
use App\Workflow\Learning\Model\HomeworkTextView;
use App\Workflow\Learning\Model\LessonStageView;
use App\Workflow\Learning\Model\TaskAudiences;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ShowHomeworkProgressWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private HomeworkReports $homework,
        private StructureLookup $structure,
        private StageRosters $rosters,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $id): HomeworkProgress
    {
        $this->access->require(Action::Read, Resource::Members);

        $reading = $this->homework->reading($id);
        $task = $reading->task;
        $stage = $this->structure->getStage($task->stageId()->toRfc4122());
        $audiences = $this->audiencesOf($reading, $stage);

        return new HomeworkProgress(
            stage: LessonStageView::of($stage),
            today: $reading->reports->today->toString(),
            item: HomeworkItems::itemOf($audiences, $task),
            text: HomeworkTextView::of($task),
            rows: AlphabeticalRows::of($audiences->rowsOf($task)),
        );
    }

    private function audiencesOf(HomeworkReading $reading, Stage $stage): TaskAudiences
    {
        $reports = $reading->reports;
        $key = AudienceKeys::keyOf($stage, $reports->today);
        $book = $this->rosters->on([$key], $reports->reporterIds());

        $accounts = AudienceAccounts::of(
            $book,
            $this->accounts->accounts(TaskAudiences::accountIdsOf($book, $reports)),
            $this->accounts->ownAccountsOf($book->personIds()),
        );

        return TaskAudiences::of($reports, new ArrayCollection([$stage->id()->toRfc4122() => $key]), $book, $accounts);
    }
}
