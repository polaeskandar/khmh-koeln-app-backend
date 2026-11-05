<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Enum;

enum ErasedSubjectKind: string
{
    case Member = 'member';
    case Account = 'account';
}
