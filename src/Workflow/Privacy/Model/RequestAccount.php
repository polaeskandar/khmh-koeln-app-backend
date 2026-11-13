<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;

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

    public static function of(?User $user, NamedAccounts $names): ?self
    {
        if ($user === null) {
            return null;
        }

        return new self(
            $user->id()->toRfc4122(),
            $names->nameOf($user),
            $user->username()->value,
            $user
                ->roles()
                ->map(static fn (Role $role): string => $role->name()->value)
                ->getValues(),
        );
    }
}
