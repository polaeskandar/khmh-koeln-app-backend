<?php

declare(strict_types=1);

namespace App\Library\Time\Model;

use App\Library\Time\Exception\InvalidLocalDate;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use JsonSerializable;
use Override;

final readonly class LocalDate implements JsonSerializable
{
    private function __construct(
        private CarbonImmutable $midnight,
    ) {
    }

    public static function of(string $value): self
    {
        $midnight = self::parsed($value);
        if ($midnight?->toDateString() !== $value) {
            throw new InvalidLocalDate($value);
        }

        return new self($midnight);
    }

    public static function ofInstant(CarbonImmutable $instant): self
    {
        return new self(
            $instant->setTimezone('UTC')->setDate($instant->year, $instant->month, $instant->day)->startOfDay(),
        );
    }

    public function addDays(int $days): self
    {
        return new self($this->midnight->addDays($days));
    }

    public function isoWeekday(): int
    {
        return $this->midnight->isoWeekday();
    }

    public function isBefore(self $other): bool
    {
        return $this->midnight->isBefore($other->midnight);
    }

    public function format(string $format): string
    {
        return $this->midnight->format($format);
    }

    public function toString(): string
    {
        return $this->midnight->toDateString();
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    private static function parsed(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
