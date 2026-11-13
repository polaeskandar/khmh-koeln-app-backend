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
use App\Context\People\Domain\ValueObject\RosterBook;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\AudienceAccounts;
use App\Workflow\Learning\Model\AudienceKeys;
use App\Workflow\Learning\Model\AudienceSize;
use App\Workflow\Learning\Model\HomeworkItems;
use App\Workflow\Learning\Model\HomeworkLessons;
use App\Workflow\Learning\Model\HomeworkList;
use App\Workflow\Learning\Model\LessonStageView;
use App\Workflow\Learning\Model\StageHomeworkFacts;
use App\Workflow\Learning\Model\TaskAudiences;
use App\Workflow\Organization\Model\StageSummary;
use App\Workflow\Organization\Model\StructureGroup;
use Doctrine\Common\Collections\ArrayCollection;

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

        $stage = $this->structure->getStage(Ids::toUuid($stageId, notFound: 'stage'));
        $this->access->requireIn(Action::Read, Resource::Homework, StructureGroup::ofStage($stage), missing: 'stage');
        $homework = $this->homework->ofStage($stage->id());
        $reports = $homework->reports;
        $key = AudienceKeys::keyOf($stage, $reports->today);
        $book = $this->rosters->getRosterBookOn([$key], $reports->reporterIds());

        return new HomeworkList(
            stage: StageSummary::of($stage),
            facts: $this->factsOf($homework, $stage, AudienceSize::of($book, $key)),
            lessons: HomeworkLessons::of($homework->lessons, $reports->today),
            items: HomeworkItems::of($reports, $this->audiencesOf($stage, $homework, $book, $key)),
        );
    }

    private function audiencesOf(Stage $stage, StageHomework $homework, RosterBook $book, RosterKey $key): TaskAudiences
    {
        $reports = $homework->reports;
        $keys = new ArrayCollection([$stage->id()->toRfc4122() => $key]);

        $accounts = AudienceAccounts::of(
            $book,
            $this->accounts->namedByIds(TaskAudiences::accountIdsOf($book, $reports)),
            $this->accounts->listOwnAccountsOf($book->personIds()),
        );

        return TaskAudiences::of($reports, $keys, $book, $accounts);
    }

    private function factsOf(StageHomework $homework, Stage $stage, int $audienceSize): StageHomeworkFacts
    {
        return new StageHomeworkFacts(
            today: $homework->reports->today->toString(),
            tabs: $homework->tabs(),
            audienceSize: $audienceSize,
            archived: LessonStageView::of($stage)->archived,
        );
    }
}
