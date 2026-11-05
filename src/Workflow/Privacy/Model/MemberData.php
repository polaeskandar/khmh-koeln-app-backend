<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class MemberData
{
    public function __construct(
        public string $exportedAt,
        public MemberRecord $member,
        public MemberSections $sections,
    ) {
    }
}
