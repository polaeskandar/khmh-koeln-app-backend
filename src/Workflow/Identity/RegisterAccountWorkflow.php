<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccountRegistration;
use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Organization\Application\Service\RegistrationServices;
use App\Context\Organization\Domain\Entity\Service;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Identity\Model\AccountChoice;
use App\Workflow\Identity\Model\RegistrationChoice;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class RegisterAccountWorkflow
{
    public function __construct(
        private AccountRegistration $accounts,
        private RegistrationServices $services,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(AccountChoice $account, RegistrationChoice $registration): void
    {
        $profile = $this->profileOf($account);
        $registeredAs = RegisteredAs::from($registration->registeredAs);

        $services = $registeredAs === RegisteredAs::Servant
            ? $this->services->chosen($registration->serviceIds)
            : new ArrayCollection();

        try {
            $accountId = $this->transactions->run(fn (): ?Uuid => $this->store(
                $profile,
                $account->password,
                $registeredAs,
                $registration,
                $services,
            ));
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $this->accounts->sendConfirmation($accountId);
    }

    private function profileOf(AccountChoice $account): Profile
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
        RegistrationChoice $registration,
        ReadableCollection $services,
    ): ?Uuid {
        $accountId = $this->accounts->registerUnlessTaken(
            $profile,
            $password,
            $registeredAs,
            $registration->note,
            $registration->privacyVersion,
        );

        if ($accountId !== null) {
            $this->services->record($accountId, $services);
        }

        return $accountId;
    }
}
