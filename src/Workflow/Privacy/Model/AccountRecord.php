<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;

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

    /**
     * @param list<Role> $roles
     */
    public static function of(User $user, string $name, array $roles): self
    {
        return new self(
            identity: AccountIdentity::of($user, $name),
            moments: AccountMoments::of($user),
            privacy: PrivacyAcceptance::of($user),
            roles: new ArrayCollection(
                $roles,
            )->map(static fn (Role $role): string => $role->name()->value)->getValues(),
        );
    }
}
