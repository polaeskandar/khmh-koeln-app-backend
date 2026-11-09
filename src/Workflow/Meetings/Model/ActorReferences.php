<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ActorReferences
{
    /**
     * @param ReadableCollection<non-empty-string, Account> $accounts
     */
    private function __construct(
        private ReadableCollection $accounts,
    ) {
    }

    /**
     * @param ReadableCollection<int, Account> $accounts
     */
    public static function of(ReadableCollection $accounts): self
    {
        return new self(Collections::firstPerKey($accounts, keyOf: self::key(...)));
    }

    /**
     * @param ReadableCollection<int, SessionEvent> $events
     *
     * @return list<Uuid>
     */
    public static function actorIdsOf(ReadableCollection $events): array
    {
        return Collections::mapPresent(
            $events,
            static fn (SessionEvent $event): ?Uuid => $event->actor()->accountId,
        )->getValues();
    }

    public function actorOf(SessionEvent $event): ?AccountReference
    {
        $actorId = $event->actor()->accountId?->toRfc4122();
        $account = $actorId === null ? null : $this->accounts->get($actorId);

        return $account === null ? null : AccountReference::of($account);
    }

    /**
     * @return non-empty-string
     */
    private static function key(Account $account): string
    {
        return $account->id()->toRfc4122();
    }
}
