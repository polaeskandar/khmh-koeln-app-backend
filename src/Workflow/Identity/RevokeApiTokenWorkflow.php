<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\ApiTokens;
use SensitiveParameter;

final readonly class RevokeApiTokenWorkflow
{
    public function __construct(
        private ApiTokens $tokens,
    ) {
    }

    public function __invoke(#[SensitiveParameter] string $token): void
    {
        $this->tokens->revoke($token);
    }
}
