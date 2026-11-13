<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\HomeworkReport;
use App\Context\People\Domain\Entity\Person;
use App\Workflow\Identity\Model\AccountReference;
use DateTimeImmutable;

final readonly class ReportView
{
    public function __construct(
        public DateTimeImmutable $completedAt,
        public ?AccountReference $completedBy,
        public bool $completedAfterDueDate,
        public bool $bySelf,
    ) {
    }

    public static function of(?HomeworkReport $report, AudienceAccounts $accounts, Person $person): ?self
    {
        if ($report === null) {
            return null;
        }

        $completer = $accounts->users->find($report->completedBy);

        return new self(
            $report->completedAt,
            $completer === null ? null : AccountReference::of($completer, $accounts->users),
            $report->late,
            bySelf: $accounts->isOwnAccount($person, $report->completedBy),
        );
    }
}
