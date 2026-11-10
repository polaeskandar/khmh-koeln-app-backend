<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class CurrentAccount
{
    public function __construct(
        public AccountSummary $account,
        public AccountAccess $access,
        public string $today,
        public ?MemberSummary $member,
    ) {
    }
}
