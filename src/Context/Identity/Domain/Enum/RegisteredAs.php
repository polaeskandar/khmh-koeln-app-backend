<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Enum;

use Doctrine\Common\Collections\ArrayCollection;

enum RegisteredAs: string
{
    case Parent = 'parent';
    case Servant = 'servant';
    case Member = 'member';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return new ArrayCollection(self::cases())->map(static fn (self $case): string => $case->value)->getValues();
    }
}
