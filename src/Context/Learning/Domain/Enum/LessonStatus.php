<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Enum;

enum LessonStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Completed = 'completed';
}
