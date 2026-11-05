<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ActorReferences
{
    /**
     * @param ReadableCollection<non-empty-string, User> $accounts
     */
    private function __construct(
        private ReadableCollection $accounts,
    ) {
    }

    /**
     * @param ReadableCollection<int, SessionEvent> $events
     */
    public static function lookedUpIn(AccountLookup $lookup, ReadableCollection $events): self
    {
        $actorIds = Collections::mapPresent(
            $events,
            static fn (SessionEvent $event): ?Uuid => $event->actor()->accountId,
        );

        return new self(Collections::firstPerKey($lookup->accounts($actorIds->getValues()), keyOf: self::key(...)));
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
    private static function key(User $user): string
    {
        return $user->id()->toRfc4122();
    }
}
