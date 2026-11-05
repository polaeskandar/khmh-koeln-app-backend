<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccountMailer;

final readonly class SendTestMailWorkflow
{
    public function __construct(
        private AccountMailer $mailer,
    ) {
    }

    public function __invoke(string $email): void
    {
        $this->mailer->sendTest($email);
    }
}
