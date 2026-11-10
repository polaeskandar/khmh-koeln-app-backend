<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Learning\Model\HomeworkTextView;
use App\Workflow\Learning\Model\MemberHomeworkDetail;
use App\Workflow\Learning\Model\OwnHomework;
use App\Workflow\Learning\Model\ParentHomeworkItems;
use Symfony\Component\Uid\Uuid;

final readonly class ShowMemberHomeworkWorkflow
{
    public function __construct(
        private MemberCheck $member,
        private AccountLookup $accounts,
        private MemberRecords $records,
        private PupilHomework $homework,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $viewerId, string $homeworkId): MemberHomeworkDetail
    {
        $viewer = Uuid::fromString($viewerId);
        $own = $this->ownOf($viewer);
        $shown = $own->requireTask(Ids::toUuidOrNull($homeworkId));

        return new MemberHomeworkDetail(
            today: $own->today->toString(),
            item: $this->itemsOf([$shown], $viewer)->itemOf($shown),
            text: HomeworkTextView::of($shown->task),
            lesson: HomeworkLessonView::of($shown->task->lesson()),
        );
    }

    private function ownOf(Uuid $viewer): OwnHomework
    {
        $record = $this->records->ownRecord($this->member->require($viewer));

        return OwnHomework::of($record, $this->homework->tasksOf(OwnHomework::pupilsOf($record)));
    }

    /**
     * @param list<VisibleTask> $tasks
     */
    private function itemsOf(array $tasks, Uuid $viewer): ParentHomeworkItems
    {
        return ParentHomeworkItems::of(
            $this->structure->stages(ParentHomeworkItems::stageIdsOf($tasks)),
            $this->accounts->accounts(ParentHomeworkItems::reporterIdsOf($tasks)),
            $viewer,
        );
    }
}
