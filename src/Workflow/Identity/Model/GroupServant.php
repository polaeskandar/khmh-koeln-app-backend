<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\Account;

final readonly class GroupServant
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
    ) {
    }

    public static function of(Account $account, string $role): self
    {
        return new self($account->id()->toRfc4122(), $account->profile()->displayName->value, $role);
    }
}
