<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use DateTimeImmutable;

final readonly class ApprovalView
{
    public function __construct(
        public ?DateTimeImmutable $approvedAt,
        public ?AccountReference $approvedBy,
    ) {
    }

    public static function of(?string $approvedAt, ?string $approverId, ?string $approverName): self
    {
        $at = $approvedAt === null ? null : new DateTimeImmutable($approvedAt);
        $by = $approverId === null ? null : new AccountReference($approverId, $approverName ?? '');

        return new self($at, $by);
    }
}
