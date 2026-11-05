<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;

final readonly class RequestAccount
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public array $roles,
    ) {
    }

    public static function of(?User $user): ?self
    {
        if ($user === null) {
            return null;
        }

        $profile = $user->profile();

        return new self(
            $user->id()->toRfc4122(),
            $profile->displayName->value,
            $profile->email->value,
            $user
                ->roles()
                ->map(static fn (Role $role): string => $role->name()->value)
                ->getValues(),
        );
    }
}
