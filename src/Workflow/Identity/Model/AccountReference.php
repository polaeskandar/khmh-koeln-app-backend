<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AccountReference
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    public static function of(User $user, NamedAccounts $names): self
    {
        return new self($user->id()->toRfc4122(), $names->nameOf($user));
    }

    /**
     * @return list<self> by name
     */
    public static function listOf(NamedAccounts $accounts): array
    {
        return new ArrayCollection($accounts->byName())->map(
            static fn (User $account): self => self::of($account, $accounts),
        )->getValues();
    }
}
