<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class HomeworkLessons
{
    /**
     * @param list<LessonOnDate> $lessons
     */
    private function __construct(
        public array $lessons,
        public ?string $defaultLessonId,
    ) {
    }

    /**
     * @param ReadableCollection<int, Lesson> $lessons
     */
    public static function of(ReadableCollection $lessons, LocalDate $today): self
    {
        $open = $lessons->filter(static fn (Lesson $lesson): bool => $lesson->state()->archivedAt === null);
        $newestFirst = Collections::sortedDescendingBy($open, keyOf: self::dateKey(...));

        $upcoming = Collections::sortedBy(
            $open->filter(static fn (Lesson $lesson): bool => !$lesson->link()->teachingDate->isBefore($today)),
            keyOf: self::dateKey(...),
        );

        $default = $upcoming[0] ?? $newestFirst[0] ?? null;

        return new self(
            new ArrayCollection($newestFirst)->map(LessonOnDate::of(...))->getValues(),
            $default?->id()->toRfc4122(),
        );
    }

    private static function dateKey(Lesson $lesson): string
    {
        return $lesson->link()->teachingDate->toString() . ' ' . $lesson->id()->toRfc4122();
    }
}
