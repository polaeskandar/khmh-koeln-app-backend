<?php

declare(strict_types=1);

namespace App\Library\Time\Client;

use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use App\Library\Time\Model\Period;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class Calendar
{
    public function __construct(
        private ClockInterface $clock,
        #[Autowire(param: 'app.time_zone')]
        private string $timeZone,
    ) {
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->clock->now())->setTimezone($this->timeZone);
    }

    public function today(): LocalDate
    {
        return $this->localDateOf($this->clock->now());
    }

    public function tomorrow(): LocalDate
    {
        return $this->today()->addDays(1);
    }

    public function instantAt(LocalDate $date, LocalTime $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toString() . ' ' . $time->toString(), $this->timeZone);
    }

    public function localDateOf(DateTimeInterface $instant): LocalDate
    {
        return CarbonImmutable::instance($instant)->setTimezone($this->timeZone) |> LocalDate::ofInstant(...);
    }

    public function formatInstant(DateTimeInterface $instant): string
    {
        return CarbonImmutable::instance($instant)->setTimezone($this->timeZone)->format(DateTimeInterface::ATOM);
    }

    public function dayBounds(LocalDate $date): Period
    {
        return new Period(
            start: $this->instantAt($date, LocalTime::midnight()),
            end: $this->instantAt($date->addDays(1), LocalTime::midnight()),
        );
    }
}
