<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\EmailConfirmations;
use SensitiveParameter;

final readonly class ConfirmEmailWorkflow
{
    public function __construct(
        private EmailConfirmations $confirmations,
    ) {
    }

    public function __invoke(#[SensitiveParameter] string $token): bool
    {
        return $this->confirmations->confirm($token);
    }
}
