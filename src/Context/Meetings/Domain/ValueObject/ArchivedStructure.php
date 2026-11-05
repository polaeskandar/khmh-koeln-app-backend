<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ArchivedStructure
{
    /**
     * @param list<string> $ids
     */
    private function __construct(
        private array $ids,
    ) {
    }

    /**
     * @param list<Uuid> $ids
     */
    public static function of(array $ids): self
    {
        return new self(new ArrayCollection($ids)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues());
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return ReadableCollection<int, Schedule>
     */
    public function generating(ReadableCollection $schedules): ReadableCollection
    {
        return $schedules->filter(fn (Schedule $schedule): bool => $schedule->isActive() && !$this->holds($schedule));
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return ReadableCollection<int, Schedule>
     */
    public function inOpenServices(ReadableCollection $schedules): ReadableCollection
    {
        $ids = new ArrayCollection($this->ids);

        return $schedules->filter(
            static fn (Schedule $schedule): bool => !$ids->contains($schedule->roster()->serviceId->toRfc4122()),
        );
    }

    private function holds(Schedule $schedule): bool
    {
        $ids = new ArrayCollection($this->ids);
        $roster = $schedule->roster();

        return $ids->contains($roster->serviceId->toRfc4122()) || $ids->contains($roster->stageId?->toRfc4122());
    }
}
