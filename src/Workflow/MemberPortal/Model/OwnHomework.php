<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Application\Service\MemberRecords;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use App\Workflow\ParentPortal\Model\ChildHomework;
use App\Workflow\ParentPortal\Model\ChildHomeworks;
use Symfony\Component\Uid\Uuid;

final readonly class OwnHomework
{
    private function __construct(
        public LocalDate $today,
        public ?ChildHomework $homework,
    ) {
    }

    public static function lookedUpIn(MemberRecords $records, PupilHomework $homework, Uuid $personId): self
    {
        $record = $records->ownRecord($personId);

        return new self($record->today, ChildHomeworks::lookedUpIn($record, $homework)[0] ?? null);
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

    public function requireTask(string $homeworkId): VisibleTask
    {
        return $this->task(Uuid::fromString($homeworkId)) ?? self::notAvailable();
    }

    public static function notAvailable(): never
    {
        throw NotFound::coded('homework.notAvailable');
    }
}
