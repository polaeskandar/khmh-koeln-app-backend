<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Time\Model\LocalDate;

final readonly class LessonWindow
{
    private const int DAYS_BACK = 28;

    private const int DAYS_AHEAD = 84;

    private function __construct(
        public LocalDate $today,
        public LocalDate $from,
        public LocalDate $to,
    ) {
    }

    public static function around(LocalDate $today, ?Lesson $edited): self
    {
        $from = $today->addDays(-self::DAYS_BACK);
        $teachingDate = $edited?->link()->teachingDate ?? $from;

        return new self(
            $today,
            from: $teachingDate->isBefore($from) ? $teachingDate : $from,
            to: $today->addDays(self::DAYS_AHEAD),
        );
    }
}
