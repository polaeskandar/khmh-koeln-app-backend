<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Model\LocalDate;

final readonly class AgendaRange
{
    private const int DAYS_BEFORE = 6;

    private const int DAYS_AFTER = 7;

    private const int MAX_DAYS = 120;

    private function __construct(
        public LocalDate $today,
        public LocalDate $from,
        public LocalDate $to,
    ) {
    }

    public static function of(?string $from, ?string $to, LocalDate $today): self
    {
        $start = self::dateOr($from, $today->addDays(-self::DAYS_BEFORE));
        $end = self::dateOr($to, $today->addDays(self::DAYS_AFTER));

        if ($end->isBefore($start) || $start->addDays(self::MAX_DAYS)->isBefore($end)) {
            throw InvalidInput::field('to', 'validation.invalidRange', ['max' => self::MAX_DAYS]);
        }

        return new self($today, $start, $end);
    }

    public static function ahead(LocalDate $today, int $days): self
    {
        return new self($today, $today->addDays(1), $today->addDays($days));
    }

    public function plannedFrom(): LocalDate
    {
        return $this->from->isBefore($this->today) ? $this->today : $this->from;
    }

    private static function dateOr(?string $date, LocalDate $default): LocalDate
    {
        return $date === null ? $default : LocalDate::of($date);
    }
}
