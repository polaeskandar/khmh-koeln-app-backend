<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Enum;

use Doctrine\Common\Collections\ArrayCollection;

enum LessonStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Completed = 'completed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return new ArrayCollection(self::cases())->map(static fn (self $case): string => $case->value)->getValues();
    }
}
