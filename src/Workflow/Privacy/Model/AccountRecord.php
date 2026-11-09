<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\Role;

final readonly class AccountRecord
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public AccountIdentity $identity,
        public AccountMoments $moments,
        public PrivacyAcceptance $privacy,
        public array $roles,
        public ?RegistrationView $registration,
    ) {
    }

    public static function of(Account $account): self
    {
        return new self(
            identity: AccountIdentity::of($account),
            moments: AccountMoments::of($account),
            privacy: PrivacyAcceptance::of($account),
            roles: $account
                ->roles()
                ->map(static fn (Role $role): string => $role->name()->value)
                ->getValues(),
            registration: RegistrationView::pendingOf($account),
        );
    }
}
