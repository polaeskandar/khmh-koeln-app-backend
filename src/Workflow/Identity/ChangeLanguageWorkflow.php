<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\CurrentAccounts;
use App\Context\Identity\Domain\Enum\Language;
use App\Library\Problem\Exception\InvalidInput;
use Symfony\Component\Uid\Uuid;

final readonly class ChangeLanguageWorkflow
{
    public function __construct(
        private CurrentAccounts $accounts,
    ) {
    }

    public function __invoke(string $accountId, string $language): void
    {
        $chosen = Language::tryFrom($language) ?? throw InvalidInput::field('language', 'validation.invalidType');

        $this->accounts->changeLanguage(Uuid::fromString($accountId), $chosen);
    }
}
