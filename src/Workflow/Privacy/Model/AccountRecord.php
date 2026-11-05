<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Library\Time\Client\Calendar;

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

    public static function of(User $user, Calendar $calendar): self
    {
        return new self(
            identity: AccountIdentity::of($user),
            moments: AccountMoments::of($user, $calendar),
            privacy: PrivacyAcceptance::of($user, $calendar),
            roles: $user
                ->roles()
                ->map(static fn (Role $role): string => $role->name()->value)
                ->getValues(),
            registration: RegistrationView::pendingOf($user),
        );
    }
}
