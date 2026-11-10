<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Enum;

use Doctrine\Common\Collections\ArrayCollection;

enum RequestKind: string
{
    case Erasure = 'erasure';
    case Copy = 'copy';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return new ArrayCollection(self::cases())->map(static fn (self $case): string => $case->value)->getValues();
    }
}
