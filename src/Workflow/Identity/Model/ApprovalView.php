<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Library\Time\Client\Calendar;
use DateTimeImmutable;

final readonly class ApprovalView
{
    public function __construct(
        public ?string $approvedAt,
        public ?AccountReference $approvedBy,
    ) {
    }

    public static function of(?string $approvedAt, ?string $approverId, ?string $approverName, Calendar $calendar): self
    {
        $at = $approvedAt === null ? null : $calendar->formatInstant(new DateTimeImmutable($approvedAt));
        $by = $approverId === null ? null : new AccountReference($approverId, $approverName ?? '');

        return new self($at, $by);
    }
}
