<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\DisabledAccounts;

final readonly class EnableAccountWorkflow
{
    public function __construct(
        private DisabledAccounts $accounts,
    ) {
    }

    public function __invoke(string $email): void
    {
        $this->accounts->enable($email);
    }
}
