<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccountRegistration;
use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Context\Organization\Domain\Entity\Service;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Identity\Model\AccountDetails;
use App\Workflow\Identity\Model\RegistrationDetails;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SensitiveParameter;

final readonly class RegisterAccountWorkflow
{
    public function __construct(
        private AccountRegistration $accounts,
        private RegistrationServices $services,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(AccountDetails $account, RegistrationDetails $registration): void
    {
        $profile = $this->profileOf($account);
        $registeredAs = RegisteredAs::from($registration->registeredAs);

        $services = $registeredAs === RegisteredAs::Servant
            ? $this->services->chosen($registration->serviceIds)
            : new ArrayCollection();

        try {
            $this->transactions->run(fn () => $this->store(
                $profile,
                $account->password,
                $registeredAs,
                $registration,
                $services,
            ));
        } catch (UniqueConstraintViolationException) {
            return;
        }
    }

    private function profileOf(AccountDetails $account): Profile
    {
        return $this->accounts->profile($account->firstName, $account->lastName, $account->email, $account->language);
    }

    /**
     * @param ReadableCollection<int, Service> $services
     */
    private function store(
        Profile $profile,
        #[SensitiveParameter]
        string $password,
        RegisteredAs $registeredAs,
        RegistrationDetails $registration,
        ReadableCollection $services,
    ): void {
        $accountId = $this->accounts->registerUnlessTaken(
            $profile,
            $password,
            $registeredAs,
            $registration->note,
            $registration->privacyVersion,
        );

        if ($accountId === null) {
            return;
        }

        $this->services->record($accountId, $services);
    }
}
