<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\PasswordLinks;
use App\Platform\Http\Contract\AfterResponseInterface;

final readonly class RequestPasswordResetWorkflow
{
    public function __construct(
        private PasswordLinks $links,
        private AfterResponseInterface $afterResponse,
    ) {
    }

    public function __invoke(string $email): void
    {
        $this->afterResponse->run('password_reset', fn () => $this->links->resetFor($email));
    }
}
