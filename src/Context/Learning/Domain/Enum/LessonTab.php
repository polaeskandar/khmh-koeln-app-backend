<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Enum;

enum LessonTab: string
{
    case Upcoming = 'upcoming';
    case Previous = 'previous';
    case Archived = 'archived';
}
