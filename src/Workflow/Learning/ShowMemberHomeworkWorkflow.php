<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
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
        $own = $this->ownOf($viewerId);
        $shown = $own->requireTask($homeworkId);

        return new MemberHomeworkDetail(
            today: $own->today->toString(),
            item: $this->itemsOf([$shown], $viewerId)->itemOf($shown),
            text: HomeworkTextView::of($shown->task),
            lesson: HomeworkLessonView::of($shown->task->lesson()),
        );
    }

    private function ownOf(string $viewerId): OwnHomework
    {
        $record = $this->records->ownRecord($this->member->require($viewerId));

        return OwnHomework::of($record, $this->homework->of(OwnHomework::pupilsOf($record)));
    }

    /**
     * @param list<VisibleTask> $tasks
     */
    private function itemsOf(array $tasks, string $viewerId): ParentHomeworkItems
    {
        return ParentHomeworkItems::of(
            $this->structure->stages(ParentHomeworkItems::stageIdsOf($tasks)),
            $this->accounts->accounts(ParentHomeworkItems::reporterIdsOf($tasks)),
            Uuid::fromString($viewerId),
        );
    }
}
