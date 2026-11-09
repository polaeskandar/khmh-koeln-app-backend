<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\Pupil;
use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Domain\ValueObject\PeopleWithGroups;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class OwnHomework
{
    private function __construct(
        public LocalDate $today,
        public ?ChildHomework $homework,
    ) {
    }

    /**
     * @return list<Pupil>
     */
    public static function pupilsOf(PeopleWithGroups $record): array
    {
        return VisibleHomework::pupilsOf($record);
    }

    /**
     * @param list<PupilTasks> $tasks
     */
    public static function of(PeopleWithGroups $record, array $tasks): self
    {
        return new self($record->today, ChildHomeworks::of($record, $tasks)[0] ?? null);
    }

    /**
     * @return list<VisibleTask>
     */
    public function tasks(): array
    {
        return $this->homework?->tasks->tasks ?? [];
    }

    public function task(Uuid $homeworkId): ?VisibleTask
    {
        return $this->homework?->tasks->task($homeworkId);
    }

    public function getTask(string $homeworkId): VisibleTask
    {
        $task = Uuid::isValid($homeworkId) ? $this->task(Uuid::fromString($homeworkId)) : null;

        return $task ?? self::notAvailable();
    }

    public static function notAvailable(): never
    {
        throw NotFound::coded('homework.notAvailable');
    }
}
