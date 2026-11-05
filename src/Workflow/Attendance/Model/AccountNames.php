<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountNames
{
    /**
     * @param ReadableCollection<string, User> $accounts
     */
    private function __construct(
        private ReadableCollection $accounts,
    ) {
    }

    /**
     * @param list<?Uuid> $ids
     */
    public static function lookedUpIn(AccountLookup $lookup, array $ids): self
    {
        $unique = Collections::firstPerKey(
            Collections::mapPresent(new ArrayCollection($ids), static fn (?Uuid $id): ?Uuid => $id),
            keyOf: static fn (Uuid $id): string => $id->toRfc4122(),
        );

        return new self(Collections::firstPerKey($lookup->accounts($unique->getValues()), keyOf: self::keyOf(...)));
    }

    public function of(?Uuid $id): ?AccountReference
    {
        $account = $id === null ? null : $this->accounts->get($id->toRfc4122());

        return $account === null ? null : AccountReference::of($account);
    }

    private static function keyOf(User $user): string
    {
        return $user->id()->toRfc4122();
    }
}
