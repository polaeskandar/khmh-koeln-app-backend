<?php

declare(strict_types=1);

namespace App\Library\Time\Model;

use App\Library\Time\Exception\InvalidLocalTime;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use JsonSerializable;
use Override;

final readonly class LocalTime implements JsonSerializable
{
    private const string FORMAT = 'H:i';

    private function __construct(
        private CarbonImmutable $clock,
    ) {
    }

    public static function of(string $value): self
    {
        $clock = self::parsed($value);

        if ($clock?->format(self::FORMAT) !== $value) {
            throw new InvalidLocalTime($value);
        }

        return new self($clock);
    }

    public static function ofInstant(CarbonImmutable $instant): self
    {
        return CarbonImmutable::createFromTime($instant->hour, $instant->minute, timezone: 'UTC') |> self::create(...);
    }

    public function isAfter(self $other): bool
    {
        return $this->toString() > $other->toString();
    }

    public function format(string $format): string
    {
        return $this->clock->format($format);
    }

    public function toString(): string
    {
        return $this->clock->format(self::FORMAT);
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    private static function create(CarbonImmutable $clock): self
    {
        return new self($clock);
    }

    private static function parsed(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!' . self::FORMAT, $value, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
