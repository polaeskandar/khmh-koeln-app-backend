<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\MemberSummary;

final readonly class MemberSummaryResponse
{
    public function __construct(
        public string $personId,
        public int $groupCount,
    ) {
    }

    public static function of(MemberSummary $member): self
    {
        return new self($member->personId, $member->groupCount);
    }
}
