<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;

final readonly class ParentAccountView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $username,
    ) {
    }

    public static function of(User $account, NamedAccounts $names): self
    {
        return new self($account->id()->toRfc4122(), $names->nameOf($account), $account->username()->value);
    }
}
