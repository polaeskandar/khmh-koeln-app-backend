<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\PasswordLinks;
use DateTimeImmutable;

final readonly class SendPasswordLinkByEmailWorkflow
{
    public function __construct(
        private PasswordLinks $links,
    ) {
    }

    public function __invoke(string $email): DateTimeImmutable
    {
        return $this->links->sendTo($email);
    }
}
