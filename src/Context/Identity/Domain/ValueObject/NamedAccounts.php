<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Entity\User;
use App\Library\Collections\Model\Collections;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class NamedAccounts
{
    private const string COLLATION = 'de';

    /**
     * @param ReadableCollection<non-empty-string, User> $accounts by id, in the order they were given
     * @param ReadableCollection<string, string> $names each account's name, by person id
     */
    private function __construct(
        private ReadableCollection $accounts,
        private ReadableCollection $names,
    ) {
    }

    /**
     * @param ReadableCollection<int, User> $accounts
     * @param array<string, string> $names each account's name, by person id
     */
    public static function of(ReadableCollection $accounts, array $names): self
    {
        $keyOf = static fn (User $account): string => $account->id()->toRfc4122();

        return new self(Collections::firstPerKey($accounts, keyOf: $keyOf), new ArrayCollection($names));
    }

    /**
     * @return list<User>
     */
    public function all(): array
    {
        return $this->accounts->getValues();
    }

    /**
     * @return list<User> by name, in German order
     */
    public function byName(): array
    {
        $collator = new Collator(self::COLLATION);

        return Collections::sortedBy(
            $this->accounts,
            keyOf: fn (User $account): string => (string) $collator->getSortKey($this->nameOf($account)),
        );
    }

    public function find(?Uuid $id): ?User
    {
        return $id === null ? null : $this->accounts->get($id->toRfc4122());
    }

    public function nameOf(User $account): string
    {
        return $this->names->get($account->personId()->toRfc4122()) ?? '';
    }
}
