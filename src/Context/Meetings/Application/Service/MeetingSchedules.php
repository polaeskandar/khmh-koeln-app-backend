<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingSchedules
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private PlannedDates $plannedDates,
    ) {
    }

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function listed(?string $serviceId, ?string $stageId): ReadableCollection
    {
        return $this->schedules->listed(self::idOf('serviceId', $serviceId), self::idOf('stageId', $stageId));
    }

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function recurring(?string $serviceId, ?string $stageId): ReadableCollection
    {
        return $this->listed($serviceId, $stageId)->filter(
            static fn (Schedule $schedule): bool => !$schedule->isOneOff(),
        );
    }

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function chosen(?string $scheduleId, ?string $serviceId, ?string $stageId): ReadableCollection
    {
        $id = self::idOf('scheduleId', $scheduleId);

        return $id === null ? $this->listed($serviceId, $stageId) : $this->schedules->findByIds([$id]);
    }

    public function agendaOf(
        ?string $scheduleId,
        ?string $serviceId,
        ?string $stageId,
        ArchivedStructure $archived,
        AgendaRange $range,
    ): AgendaDates {
        return $this->plannedDates->agendaOf($this->chosen($scheduleId, $serviceId, $stageId), $archived, $range);
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    public function agendaFor(
        ReadableCollection $schedules,
        ArchivedStructure $archived,
        AgendaRange $range,
    ): AgendaDates {
        return $this->plannedDates->agendaOf($schedules, $archived, $range);
    }

    public function find(string $id): Schedule
    {
        $schedule = $this->schedules->find(Uuid::fromString($id));

        return $schedule?->isOneOff() === false ? $schedule : throw NotFound::of('schedule');
    }

    private static function idOf(string $field, ?string $value): ?Uuid
    {
        if ($value === null) {
            return null;
        }

        return Uuid::isValid($value)
            ? Uuid::fromString($value)
            : throw InvalidInput::field($field, 'validation.invalidType');
    }
}
