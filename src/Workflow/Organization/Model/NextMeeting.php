<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Context\Organization\Domain\Entity\Stage;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class NextMeeting
{
    public function __construct(
        public string $date,
        public string $startTime,
        public ?StageSummary $stage,
        public string $status,
    ) {
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     */
    public static function of(PlannedDate $date, ReadableCollection $stages): self
    {
        $stageId = $date->schedule->roster()->stageId;
        $stage = $stages->filter(static fn (Stage $stage): bool => $stage->id()->equals($stageId))->first();

        return new self(
            date: $date->date->toString(),
            startTime: $date->plan->timeSlot->startTime->toString(),
            stage: $stage instanceof Stage ? StageSummary::of($stage) : null,
            status: $date->status,
        );
    }
}
