<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ReportAboutRow
{
    public function __construct(
        public string $homework,
        public ?string $completedAt,
        public ?string $undoneAt,
    ) {
    }

    /**
     * @param ReadableCollection<int, HomeworkCompletion> $reports
     *
     * @return list<self>
     */
    public static function listOf(ReadableCollection $reports, Calendar $calendar): array
    {
        return $reports->map(
            static fn (HomeworkCompletion $report): self => new self(
                $report->assignment()->text()->title,
                ExportInstant::of($report->completedAt(), $calendar),
                ExportInstant::of($report->undoneAt(), $calendar),
            ),
        )->getValues();
    }
}
