<?php

declare(strict_types=1);

namespace App\Workflow\Access\Model;

use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\ValueObject\Access;

final readonly class PermissionGrant
{
    public function __construct(
        public Action $action,
        public Resource $resource,
    ) {
    }

    public static function of(Access $access): self
    {
        return new self($access->action, $access->resource);
    }
}
