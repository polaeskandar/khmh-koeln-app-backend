<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Application\Service\MemberRecords;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\OwnHomework;
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
        $viewer = Uuid::fromString($viewerId);
        $personId = $this->member->require($viewer);

        $undone = $this->completions->whileLocked(
            Ids::toUuidOrNull($homeworkId),
            fn (HomeworkAssignment $task): VisibleTask => $this->undone($personId, $viewer, $task),
        );

        if ($undone === null) {
            OwnHomework::notAvailable();
        }
    }

    private function undone(Uuid $personId, Uuid $viewer, HomeworkAssignment $task): VisibleTask
    {
        $record = $this->records->ownRecord($personId);
        $own = OwnHomework::of($record, $this->homework->of(OwnHomework::pupilsOf($record)));

        return $this->completions->undo($own->requireTask($task->id()), $personId, by: $viewer);
    }
}
