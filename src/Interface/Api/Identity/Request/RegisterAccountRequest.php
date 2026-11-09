<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use App\Workflow\Identity\Model\AccountChoice;
use App\Workflow\Identity\Model\RegistrationChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterAccountRequest
{
    public function __construct(
        #[Assert\Valid]
        public AccountRequest $account = new AccountRequest(),
        #[Assert\Valid]
        public RegistrationRequest $registration = new RegistrationRequest(),
        #[Assert\Valid]
        public PrivacyRequest $privacy = new PrivacyRequest(),
    ) {
    }

    public function accountChoice(): AccountChoice
    {
        return $this->account->choice();
    }

    public function registrationChoice(): RegistrationChoice
    {
        return new RegistrationChoice(
            $this->registration->registeredAs ?? '',
            $this->registration->note ?? '',
            $this->registration->serviceIds,
            $this->privacy->version ?? '',
        );
    }
}
