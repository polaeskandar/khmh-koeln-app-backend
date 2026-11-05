<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\MemberPortal\Model\OwnHomework;
use Symfony\Component\Uid\Uuid;

final readonly class UndoOwnReportWorkflow
{
    public function __construct(
        private MemberCheck $member,
        private MemberRecords $records,
        private PupilHomework $homework,
        private HomeworkCompletions $completions,
    ) {
    }

    public function __invoke(string $viewerId, string $homeworkId): void
    {
        $personId = $this->member->require($viewerId);
        $viewer = Uuid::fromString($viewerId);

        $this->completions->whileLocked(
            $homeworkId,
            fn (HomeworkAssignment $task): VisibleTask => $this->undone($personId, $viewer, $task),
            OwnHomework::notAvailable(...),
        );
    }

    private function undone(Uuid $personId, Uuid $viewer, HomeworkAssignment $task): VisibleTask
    {
        $record = $this->records->ownRecord($personId);
        $own = OwnHomework::of($record, $this->homework->of(OwnHomework::pupilsOf($record)));

        return $this->completions->undo($own->requireTask($task->id()->toRfc4122()), $personId, by: $viewer);
    }
}
