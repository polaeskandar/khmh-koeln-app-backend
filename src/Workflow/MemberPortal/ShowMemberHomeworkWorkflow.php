<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Learning\Model\HomeworkTextView;
use App\Workflow\MemberPortal\Model\MemberHomeworkDetail;
use App\Workflow\MemberPortal\Model\OwnHomework;
use App\Workflow\ParentPortal\Model\ParentHomeworkItems;
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
        $own = OwnHomework::lookedUpIn($this->records, $this->homework, $this->member->require($viewerId));
        $shown = $own->requireTask($homeworkId);

        $items = ParentHomeworkItems::lookedUpIn(
            $this->structure,
            $this->accounts,
            [$shown],
            Uuid::fromString($viewerId),
        );

        return new MemberHomeworkDetail(
            today: $own->today->toString(),
            item: $items->itemOf($shown),
            text: HomeworkTextView::of($shown->task),
            lesson: HomeworkLessonView::of($shown->task->lesson()),
        );
    }
}
