<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class MemberAccountItem
{
    public function __construct(
        public MemberAccountView $account,
        public MemberRecordView $member,
        public MemberAgeView $age,
    ) {
    }
}
