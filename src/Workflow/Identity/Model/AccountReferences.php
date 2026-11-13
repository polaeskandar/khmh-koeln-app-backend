<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\InputText;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountReferences
{
    private function __construct(
        private NamedAccounts $accounts,
    ) {
    }

    public static function of(NamedAccounts $accounts): self
    {
        return new self($accounts);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<AccountReference>
     */
    public function byNameOf(array $ids): array
    {
        $present = Collections::mapPresent(new ArrayCollection($ids), $this->accounts->find(...));

        return new ArrayCollection(Collections::sortedBy(
            $present,
            keyOf: $this->nameKeyOf(...),
        ))->map(fn (User $account): AccountReference => AccountReference::of($account, $this->accounts))->getValues();
    }

    public function referenceOf(?Uuid $id): ?AccountReference
    {
        $account = $this->accounts->find($id);

        return $account === null ? null : AccountReference::of($account, $this->accounts);
    }

    private function nameKeyOf(User $account): string
    {
        $name = InputText::of($this->accounts->nameOf($account))->lowered()->toString();

        return $name . ' ' . $account->id()->toRfc4122();
    }
}
