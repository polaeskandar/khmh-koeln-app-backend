<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use App\Workflow\Identity\Model\AccountChoice;
use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class NewAccountRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'accounts.firstNameRequired', normalizer: 'trim')]
        #[Assert\Length(max: 80, maxMessage: 'validation.tooLong')]
        public ?string $firstName = null,
        #[Assert\NotBlank(message: 'accounts.lastNameRequired', normalizer: 'trim')]
        #[Assert\Length(max: 80, maxMessage: 'validation.tooLong')]
        public ?string $lastName = null,
        #[Assert\NotBlank(message: 'accounts.emailRequired', normalizer: 'trim')]
        #[Assert\Email(message: 'accounts.emailInvalid', normalizer: 'trim')]
        #[Assert\Length(max: 180, maxMessage: 'validation.tooLong')]
        public ?string $email = null,
        #[SensitiveParameter]
        #[Assert\NotBlank(message: 'accounts.passwordTooShort')]
        #[Assert\Length(min: 10, max: 200, minMessage: 'accounts.passwordTooShort', maxMessage: 'validation.tooLong')]
        public ?string $password = null,
        #[Assert\NotBlank(message: 'accounts.chooseLanguage')]
        #[Assert\Choice(choices: ['en', 'de', 'ar'], message: 'accounts.chooseLanguage')]
        public ?string $language = null,
    ) {
    }

    public function choice(): AccountChoice
    {
        return new AccountChoice(
            $this->firstName ?? '',
            $this->lastName ?? '',
            $this->email ?? '',
            $this->password ?? '',
            $this->language ?? '',
        );
    }
}
