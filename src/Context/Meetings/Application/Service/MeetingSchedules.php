<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingSchedules
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
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
    public function chosen(?string $scheduleId, ?string $serviceId, ?string $stageId): ReadableCollection
    {
        $id = self::idOf('scheduleId', $scheduleId);

        return $id === null ? $this->listed($serviceId, $stageId) : $this->schedules->findByIds([$id]);
    }

    public function find(string $id): Schedule
    {
        return $this->schedules->find(Uuid::fromString($id)) ?? throw NotFound::of('schedule');
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
