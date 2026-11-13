<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\ValueObject;

use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;

final readonly class Access
{
    public const string ATTRIBUTE = 'access';

    public function __construct(
        public Action $action,
        public Resource $resource,
    ) {
    }
}
