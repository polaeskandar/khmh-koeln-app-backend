<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccountLifecycle;

final readonly class DisableUserWorkflow
{
    public function __construct(
        private AccountLifecycle $accounts,
    ) {
    }

    public function __invoke(string $username): void
    {
        $this->accounts->disable($username);
    }
}
