<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\MemberAccounts;

final readonly class UnlinkMemberWorkflow
{
    public function __construct(
        private MemberAccounts $accounts,
    ) {
    }

    public function __invoke(string $email): void
    {
        $this->accounts->unlink($email);
    }
}
