<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\PasswordChanges;
use SensitiveParameter;

final readonly class SetPasswordFromLinkWorkflow
{
    public function __construct(
        private PasswordChanges $passwords,
    ) {
    }

    public function __invoke(#[SensitiveParameter] string $token, #[SensitiveParameter] string $password): bool
    {
        return $this->passwords->changeFromLink($token, $password);
    }
}
