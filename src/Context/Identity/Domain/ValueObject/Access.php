<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class Access
{
    public const string ATTRIBUTE = 'access';

    public function __construct(
        public Action $action,
        public Resource $resource,
    ) {
    }
}
