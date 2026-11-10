<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\ApprovedAccounts;
use App\Context\Identity\Domain\Enum\Language;
use App\Library\Problem\Exception\InvalidInput;
use SensitiveParameter;

final readonly class CreateUserWorkflow
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
        $chosen = Language::tryFrom($language) ?? throw InvalidInput::field('language', 'accounts.chooseLanguage');

        return $this->accounts->create($email, $displayName, $chosen, $password, $role)->toRfc4122();
    }
}
