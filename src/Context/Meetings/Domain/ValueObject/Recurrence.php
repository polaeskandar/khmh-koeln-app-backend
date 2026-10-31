<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\Frequency;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Recurrence
{
    private const array ISO_WEEKDAYS = [1, 2, 3, 4, 5, 6, 7];

    private function __construct(
        #[ORM\Column(name: 'frequency', length: 7, enumType: Frequency::class)]
        public Frequency $frequency,
        #[ORM\Column(name: 'interval', type: Types::SMALLINT)]
        public int $interval,
        #[ORM\Column(name: 'weekdays', type: Types::SMALLINT)]
        public int $weekdayMask,
        #[ORM\Column(name: 'month_week', type: Types::SMALLINT, nullable: true)]
        public ?int $monthWeek,
        #[ORM\Embedded(DateSpan::class, columnPrefix: false)]
        public DateSpan $span,
    ) {
    }

    /**
     * @param list<int> $weekdays
     */
    public static function weekly(int $interval, array $weekdays, DateSpan $span): self
    {
        return new self(Frequency::Weekly, $interval, self::maskOf($weekdays), monthWeek: null, span: $span);
    }

    public static function monthly(int $monthWeek, int $weekday, DateSpan $span): self
    {
        return new self(
            Frequency::Monthly,
            interval: 1,
            weekdayMask: self::maskOf([$weekday]),
            monthWeek: $monthWeek,
            span: $span,
        );
    }

    /**
     * @return list<int>
     */
    public function weekdays(): array
    {
        return new ArrayCollection(self::ISO_WEEKDAYS)
            ->filter($this->includes(...))
            ->getValues();
    }

    private function includes(int $weekday): bool
    {
        return ($this->weekdayMask & self::bitOf($weekday)) !== 0;
    }

    /**
     * @param list<int> $weekdays
     */
    private static function maskOf(array $weekdays): int
    {
        return new ArrayCollection($weekdays)->reduce(
            static fn (int $mask, int $weekday): int => $mask | self::bitOf($weekday),
            initial: 0,
        );
    }

    private static function bitOf(int $weekday): int
    {
        $shift = $weekday - 1;

        return 1 << $shift;
    }
}
