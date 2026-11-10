<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Enum;

use Doctrine\Common\Collections\ArrayCollection;

enum PersonStatusFilter: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case All = 'all';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return new ArrayCollection(self::cases())->map(static fn (self $case): string => $case->value)->getValues();
    }

    public function status(): ?PersonStatus
    {
        return match ($this) {
            self::Active => PersonStatus::Active,
            self::Inactive => PersonStatus::Inactive,
            self::All => null,
        };
    }
}
