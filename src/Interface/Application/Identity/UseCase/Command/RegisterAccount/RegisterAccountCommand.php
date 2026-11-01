<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Command\RegisterAccount;

use App\Interface\Application\Identity\Input\AccountInput;
use App\Interface\Application\Identity\Input\PrivacyInput;
use App\Interface\Application\Identity\Input\RegistrationInput;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterAccountCommand
{
    public function __construct(
        #[Assert\Valid]
        public AccountInput $account = new AccountInput(),
        #[Assert\Valid]
        public RegistrationInput $registration = new RegistrationInput(),
        #[Assert\Valid]
        public PrivacyInput $privacy = new PrivacyInput(),
    ) {
    }
}
