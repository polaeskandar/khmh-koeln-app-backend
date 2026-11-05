<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Workflow\Identity\Model\AccountReference;
use DateTimeInterface;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class HistoryEntry
{
    public function __construct(
        public string $type,
        public string $at,
        public ?AccountReference $actor,
        public bool $byGenerator,
        public ?string $note,
    ) {
    }

    /**
     * @param ReadableCollection<int, SessionEvent> $events
     *
     * @return list<self>
     */
    public static function listOf(ReadableCollection $events, ActorReferences $actors, SessionAbilities $can): array
    {
        return $events->map(
            static fn (SessionEvent $event): self => new self(
                type: $event->type()->value,
                at: $event->occurredAt()->format(DateTimeInterface::ATOM),
                actor: $actors->actorOf($event),
                byGenerator: $event->actor()->isGenerator,
                note: $can->readContent ? $event->note() : null,
            ),
        )->getValues();
    }
}
