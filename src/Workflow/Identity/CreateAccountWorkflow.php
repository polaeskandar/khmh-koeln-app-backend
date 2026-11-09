<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\ApprovedAccounts;
use SensitiveParameter;

final readonly class CreateAccountWorkflow
{
    public function __construct(
        private ApprovedAccounts $accounts,
    ) {
    }

    public function __invoke(
        string $email,
        string $displayName,
        string $language,
        #[SensitiveParameter]
        ?string $password,
        ?string $role,
    ): string {
        return $this->accounts->create($email, $displayName, $language, $password, $role)->toRfc4122();
    }
}
