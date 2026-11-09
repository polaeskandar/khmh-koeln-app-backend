<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\HomeworkReportWrites;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\Learning\Model\OwnHomework;
use Symfony\Component\Uid\Uuid;

final readonly class UndoMemberHomeworkReportWorkflow
{
    public function __construct(
        private MemberCheck $member,
        private MemberRecords $records,
        private PupilHomework $homework,
        private HomeworkReportWrites $reports,
    ) {
    }

    public function __invoke(string $viewerId, string $homeworkId): void
    {
        $personId = $this->member->require($viewerId);
        $viewer = Uuid::fromString($viewerId);

        $this->reports->whileLocked(
            $homeworkId,
            fn (HomeworkTask $task): VisibleTask => $this->undone($personId, $viewer, $task),
            OwnHomework::notAvailable(...),
        );
    }

    private function undone(Uuid $personId, Uuid $viewer, HomeworkTask $task): VisibleTask
    {
        $record = $this->records->ownRecord($personId);
        $own = OwnHomework::of($record, $this->homework->visibleTo(OwnHomework::pupilsOf($record)));

        return $this->reports->undo($own->getTask($task->id()->toRfc4122()), $personId, by: $viewer);
    }
}
