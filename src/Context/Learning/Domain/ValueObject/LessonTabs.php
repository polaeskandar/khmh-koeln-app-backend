<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonTab;
use App\Library\Collections\Model\Orderings;
use App\Library\Strings\Model\SearchWords;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class LessonTabs
{
    /**
     * @param ReadableCollection<int, Lesson> $lessons
     */
    public function __construct(
        private ReadableCollection $lessons,
        private LocalDate $today,
    ) {
    }

    /**
     * @return array{upcoming: int, previous: int, archived: int}
     */
    public function counts(): array
    {
        return [
            LessonTab::Upcoming->value => $this->in(LessonTab::Upcoming)->count(),
            LessonTab::Previous->value => $this->in(LessonTab::Previous)->count(),
            LessonTab::Archived->value => $this->in(LessonTab::Archived)->count(),
        ];
    }

    /**
     * @return list<Lesson>
     */
    public function shown(LessonTab $tab, ?string $query): array
    {
        $words = SearchWords::of($query);

        $shown = $this->in($tab)->filter(static fn (Lesson $lesson): bool => $words->areAllIn(
            $lesson->text()->title . ' ' . ($lesson->text()->passage ?? ''),
        ));

        if ($tab === LessonTab::Upcoming) {
            return Orderings::byKeys($shown, keysOf: self::sortKeys(...));
        }

        return Orderings::byKeysDescending($shown, keysOf: self::sortKeys(...));
    }

    /**
     * @return ReadableCollection<int, Lesson>
     */
    private function in(LessonTab $tab): ReadableCollection
    {
        return $this->lessons->filter(fn (Lesson $lesson): bool => $this->tabOf($lesson) === $tab);
    }

    private function tabOf(Lesson $lesson): LessonTab
    {
        return match (true) {
            $lesson->state()->archivedAt !== null => LessonTab::Archived,
            $lesson->link()->teachingDate->isBefore($this->today) => LessonTab::Previous,
            default => LessonTab::Upcoming,
        };
    }

    /**
     * @return list<string>
     */
    private static function sortKeys(Lesson $lesson): array
    {
        return [$lesson->link()->teachingDate->toString(), $lesson->id()->toRfc4122()];
    }
}
