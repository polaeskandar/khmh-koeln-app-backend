<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Enum;

use Doctrine\Common\Collections\ArrayCollection;

enum Frequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return new ArrayCollection(self::cases())->map(static fn (self $case): string => $case->value)->getValues();
    }
}
