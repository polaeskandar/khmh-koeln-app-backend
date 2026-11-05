<?php

declare(strict_types=1);

namespace App\Interface\Api\Auth\Request;

use App\Workflow\Identity\Model\AccountDetails;
use App\Workflow\Identity\Model\RegistrationDetails;
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

    public function accountDetails(): AccountDetails
    {
        return $this->account->details();
    }

    public function registrationDetails(): RegistrationDetails
    {
        return new RegistrationDetails(
            $this->registration->registeredAs ?? '',
            $this->registration->note ?? '',
            $this->registration->serviceIds,
            $this->privacy->version ?? '',
        );
    }
}
