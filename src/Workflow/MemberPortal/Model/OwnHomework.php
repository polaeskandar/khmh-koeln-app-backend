<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Learning\Domain\ValueObject\Pupil;
use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Domain\ValueObject\Family;
use App\Workflow\ParentPortal\Model\ChildHomework;
use App\Workflow\ParentPortal\Model\ChildHomeworks;
use App\Workflow\ParentPortal\Model\VisibleHomework;
use Symfony\Component\Uid\Uuid;

final readonly class OwnHomework
{
    private function __construct(
        public ?ChildHomework $homework,
    ) {
    }

    /**
     * @return list<Pupil>
     */
    public static function pupilsOf(Family $record): array
    {
        return VisibleHomework::pupilsOf($record);
    }

    /**
     * @param list<PupilTasks> $tasks
     */
    public static function of(Family $record, array $tasks): self
    {
        return new self(ChildHomeworks::of($record, $tasks)[0] ?? null);
    }

    public function task(Uuid $homeworkId): ?VisibleTask
    {
        return $this->homework?->tasks->task($homeworkId);
    }
}
