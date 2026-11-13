<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\NewAccounts;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\ValueObject\AccountDraft;
use App\Library\Problem\Exception\InvalidInput;
use App\Workflow\Identity\Model\NewAccountDetails;
use SensitiveParameter;

final readonly class CreateUserWorkflow
{
    public function __construct(
        private NewAccounts $accounts,
    ) {
    }

    public function __invoke(NewAccountDetails $details, #[SensitiveParameter] string $password, ?string $role): string
    {
        $language = Language::tryFrom($details->language) ?? throw InvalidInput::field(
            'language',
            'accounts.chooseLanguage',
        );

        $draft = new AccountDraft($details->username, $details->email, $details->displayName, $language);

        return $this->accounts->create($draft, $password, $role)->toRfc4122();
    }
}
