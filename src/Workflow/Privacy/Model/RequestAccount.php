<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\Role;

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

    public static function of(?Account $account): ?self
    {
        if ($account === null) {
            return null;
        }

        $profile = $account->profile();

        return new self(
            $account->id()->toRfc4122(),
            $profile->displayName->value,
            $profile->email->value,
            $account
                ->roles()
                ->map(static fn (Role $role): string => $role->name()->value)
                ->getValues(),
        );
    }
}
