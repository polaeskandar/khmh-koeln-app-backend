<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RequestAccount
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $username,
        public array $roles,
    ) {
    }

    /**
     * @param list<Role> $roles the account's roles
     */
    public static function of(?User $user, NamedAccounts $names, array $roles): ?self
    {
        if ($user === null) {
            return null;
        }

        return new self(
            $user->id()->toRfc4122(),
            $names->nameOf($user),
            $user->username()->value,
            new ArrayCollection($roles)->map(static fn (Role $role): string => $role->name()->value)->getValues(),
        );
    }
}
