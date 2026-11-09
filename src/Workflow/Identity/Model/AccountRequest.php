<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Organization\Domain\Entity\RegistrationService;
use App\Workflow\Organization\Model\ServiceReference;
use DateTimeImmutable;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AccountRequest
{
    /**
     * @param list<ServiceReference> $services
     */
    public function __construct(
        public AccountSummary $account,
        public DateTimeImmutable $registeredAt,
        public ?string $registeredAs,
        public ?string $note,
        public array $services,
    ) {
    }

    /**
     * @param ReadableCollection<int, RegistrationService> $chosen
     */
    public static function of(Account $account, ReadableCollection $chosen): self
    {
        $registration = $account->registration();

        return new self(
            account: AccountSummary::of($account),
            registeredAt: $account->createdAt(),
            registeredAs: $registration->registeredAs?->value,
            note: $registration->note,
            services: $chosen
                ->filter(static fn (RegistrationService $service): bool => $service
                    ->accountId()
                    ->equals($account->id()))
                ->map(static fn (RegistrationService $service): ServiceReference => ServiceReference::of(
                    $service->service(),
                ))
                ->getValues(),
        );
    }
}
