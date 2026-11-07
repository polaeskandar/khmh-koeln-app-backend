<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountReferences
{
    /**
     * @param ReadableCollection<non-empty-string, User> $accounts
     */
    private function __construct(
        private ReadableCollection $accounts,
    ) {
    }

    /**
     * @param ReadableCollection<int, User> $accounts
     */
    public static function of(ReadableCollection $accounts): self
    {
        $keyOf = static fn (User $account): string => $account->id()->toRfc4122();

        return new self(Collections::firstPerKey($accounts, keyOf: $keyOf));
    }

    public function referenceOf(?Uuid $id): ?AccountReference
    {
        $account = $id === null ? null : $this->accounts->get($id->toRfc4122());

        return $account === null ? null : AccountReference::of($account);
    }
}
