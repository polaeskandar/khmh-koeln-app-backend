<?php

declare(strict_types=1);

namespace App\Library\Time\Client;

use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
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

    public function instantAt(LocalDate $date, LocalTime $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toString() . ' ' . $time->toString(), $this->timeZone);
    }

    public function localDateOf(DateTimeInterface $instant): LocalDate
    {
        return CarbonImmutable::instance($instant)->setTimezone($this->timeZone) |> LocalDate::ofInstant(...);
    }

    public function localTimeOf(DateTimeInterface $instant): LocalTime
    {
        return CarbonImmutable::instance($instant)->setTimezone($this->timeZone) |> LocalTime::ofInstant(...);
    }

    public function monthAfter(LocalDate $date): LocalDate
    {
        return LocalDate::of(CarbonImmutable::parse($date->toString(), 'UTC')->addMonthNoOverflow()->toDateString());
    }
}
