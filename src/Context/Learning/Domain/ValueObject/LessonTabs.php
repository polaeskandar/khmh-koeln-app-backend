<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonTab;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
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
     * @return array<string, int>
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
        $words = Text::of(InputText::of($query)->folded()->toString())->split(' ');
        $shown = $this->in($tab)->filter(static fn (Lesson $lesson): bool => self::matches($lesson, $words));

        if ($tab === LessonTab::Upcoming) {
            return Collections::sortedBy($shown, keyOf: self::sortKey(...));
        }

        return Collections::sortedDescendingBy($shown, keyOf: self::sortKey(...));
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
     * @param list<string> $words
     */
    private static function matches(Lesson $lesson, array $words): bool
    {
        $searched = $lesson->text()->title . ' ' . ($lesson->text()->passage ?? '');
        $text = Text::of(InputText::of($searched)->folded()->toString());

        return new ArrayCollection($words)
            ->filter(static fn (string $word): bool => !$text->contains($word))
            ->isEmpty();
    }

    private static function sortKey(Lesson $lesson): string
    {
        return $lesson->link()->teachingDate->toString() . ' ' . $lesson->id()->toRfc4122();
    }
}
