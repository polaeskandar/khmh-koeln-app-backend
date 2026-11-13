<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;

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
    ) {
    }

    public static function of(User $user, string $name): self
    {
        return new self(
            identity: AccountIdentity::of($user, $name),
            moments: AccountMoments::of($user),
            privacy: PrivacyAcceptance::of($user),
            roles: $user
                ->roles()
                ->map(static fn (Role $role): string => $role->name()->value)
                ->getValues(),
        );
    }
}
