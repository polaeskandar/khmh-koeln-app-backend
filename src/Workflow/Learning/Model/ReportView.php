<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Learning\Domain\ValueObject\HomeworkReport;
use App\Workflow\Identity\Model\AccountReference;
use DateTimeImmutable;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ReportView
{
    public function __construct(
        public DateTimeImmutable $completedAt,
        public ?AccountReference $completedBy,
        public bool $completedAfterDueDate,
    ) {
    }

    /**
     * @param ReadableCollection<string, User> $accounts
     */
    public static function of(?HomeworkReport $report, ReadableCollection $accounts): ?self
    {
        if ($report === null) {
            return null;
        }

        $completer = $accounts->get($report->completedBy?->toRfc4122() ?? '');

        return new self(
            $report->completedAt,
            $completer === null ? null : AccountReference::of($completer),
            $report->late,
        );
    }
}
