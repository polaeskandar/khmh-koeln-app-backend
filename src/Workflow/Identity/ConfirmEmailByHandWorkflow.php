<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccountRegistration;

final readonly class ConfirmEmailByHandWorkflow
{
    public function __construct(
        private AccountRegistration $accounts,
    ) {
    }

    public function __invoke(string $email): bool
    {
        return $this->accounts->confirmEmailByHand($email);
    }
}
