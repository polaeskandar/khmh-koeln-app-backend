<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Enum;

enum HomeworkStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
