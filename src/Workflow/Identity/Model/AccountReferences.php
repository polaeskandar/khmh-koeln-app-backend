<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\InputText;
use Doctrine\Common\Collections\ArrayCollection;
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

    /**
     * @param list<Uuid> $ids
     *
     * @return list<AccountReference>
     */
    public function byNameOf(array $ids): array
    {
        $present = Collections::mapPresent(new ArrayCollection($ids), fn (Uuid $id): ?User => $this->accounts->get(
            $id->toRfc4122(),
        ));

        return new ArrayCollection(Collections::sortedBy($present, keyOf: self::nameKeyOf(...)))->map(
            AccountReference::of(...),
        )->getValues();
    }

    public function referenceOf(?Uuid $id): ?AccountReference
    {
        $account = $id === null ? null : $this->accounts->get($id->toRfc4122());

        return $account === null ? null : AccountReference::of($account);
    }

    private static function nameKeyOf(User $account): string
    {
        $name = InputText::of($account->profile()->displayName->value)->lowered()->toString();

        return $name . ' ' . $account->id()->toRfc4122();
    }
}
