<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\CurrentAccounts;

final readonly class ChangeLanguageWorkflow
{
    public function __construct(
        private CurrentAccounts $accounts,
    ) {
    }

    public function __invoke(string $accountId, string $language): void
    {
        $this->accounts->changeLanguage($accountId, $language);
    }
}
