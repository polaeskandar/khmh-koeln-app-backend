<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use DateTimeImmutable;

final readonly class MemberData
{
    /**
     * @param list<MembershipRow> $memberships
     * @param list<LinkRow> $parentAccounts
     */
    public function __construct(
        public DateTimeImmutable $exportedAt,
        public MemberRecord $member,
        public array $memberships,
        public array $parentAccounts,
    ) {
    }
}
