<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Context\Scheduling\Domain\Entity\SessionEvent;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ActorReferences
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
        $account = $this->accounts->find($event->actor()->accountId);

        return $account === null ? null : AccountReference::of($account, $this->accounts);
    }
}
