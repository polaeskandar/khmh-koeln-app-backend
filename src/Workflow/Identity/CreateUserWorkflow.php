<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\NewAccounts;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\ValueObject\AccountDraft;
use App\Context\People\Application\Service\PersonWrites;
use App\Library\Problem\Exception\InvalidInput;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Identity\Model\NewAccountDetails;
use SensitiveParameter;

final readonly class CreateUserWorkflow
{
    public function __construct(
        private PersonWrites $people,
        private NewAccounts $accounts,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(NewAccountDetails $details, #[SensitiveParameter] string $password, ?string $role): string
    {
        $language = Language::tryFrom($details->language) ?? throw InvalidInput::field(
            'language',
            'accounts.chooseLanguage',
        );

        $name = $this->people->buildName($details->firstName, $details->lastName, displayName: null);

        return $this->transactions->run(function () use ($details, $name, $language, $password, $role): string {
            $person = $this->people->create($name, placement: null)->person;
            $draft = new AccountDraft($person->id(), $details->username, $details->email, $name->fullName(), $language);

            return $this->accounts->create($draft, $password, $role)->toRfc4122();
        });
    }
}
