<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\LessonWindow;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LessonOptionLookup
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private Calendar $calendar,
    ) {
    }

    public function edited(Uuid $stageId, ?string $lessonId): ?Lesson
    {
        if ($lessonId === null) {
            return null;
        }

        $lesson = $this->lessons->find(Uuid::fromString($lessonId));

        return $lesson?->stageId()->equals($stageId) === true ? $lesson : throw NotFound::of('lesson');
    }

    public function windowFor(?Lesson $edited): LessonWindow
    {
        return LessonWindow::around($this->calendar->today(), $edited);
    }

    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return ReadableCollection<int, Lesson>
     */
    public function takenIn(array $scheduleIds, LessonWindow $window, ?Lesson $edited): ReadableCollection
    {
        return $this->lessons
            ->currentBetween($scheduleIds, $window->from, $window->to)
            ->filter(static fn (Lesson $lesson): bool => $lesson !== $edited);
    }
}
