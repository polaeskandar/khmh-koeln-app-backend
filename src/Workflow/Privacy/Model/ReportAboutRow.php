<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Learning\Domain\Entity\HomeworkReport;
use DateTimeImmutable;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ReportAboutRow
{
    public function __construct(
        public string $homework,
        public DateTimeImmutable $completedAt,
        public ?DateTimeImmutable $undoneAt,
    ) {
    }

    /**
     * @param ReadableCollection<int, HomeworkReport> $reports
     *
     * @return list<self>
     */
    public static function listOf(ReadableCollection $reports): array
    {
        return $reports->map(
            static fn (HomeworkReport $report): self => new self(
                $report->task()->text()->title,
                $report->completedAt(),
                $report->undoneAt(),
            ),
        )->getValues();
    }
}
