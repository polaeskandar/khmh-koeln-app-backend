<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonWindow;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class LessonOptions
{
    /**
     * @param list<LessonOption> $options
     * @param list<AccountReference> $servants
     */
    public function __construct(
        public string $today,
        public array $options,
        public array $servants,
    ) {
    }

    /**
     * @param list<PlannedDate> $dates
     * @param ReadableCollection<int, Lesson> $taken
     * @param list<User> $preparers
     */
    public static function of(LessonWindow $window, array $dates, ReadableCollection $taken, array $preparers): self
    {
        $takenAt = Collections::firstPerKey($taken, keyOf: self::lessonKey(...));

        $optionOf = static fn (PlannedDate $date): LessonOption => new LessonOption(
            OptionDate::of($date),
            self::referenceOf($takenAt->get(self::dateKey($date))),
        );

        return new self(
            today: $window->today->toString(),
            options: new ArrayCollection($dates)->map($optionOf)->getValues(),
            servants: new ArrayCollection($preparers)->map(AccountReference::of(...))->getValues(),
        );
    }

    private static function referenceOf(?Lesson $lesson): ?LessonReference
    {
        return $lesson === null ? null : LessonReference::of($lesson);
    }

    private static function lessonKey(Lesson $lesson): string
    {
        $link = $lesson->link();

        return ($link->scheduleId?->toRfc4122() ?? '') . '|' . $link->teachingDate->toString();
    }

    private static function dateKey(PlannedDate $date): string
    {
        return $date->schedule->id()->toRfc4122() . '|' . $date->date->toString();
    }
}
