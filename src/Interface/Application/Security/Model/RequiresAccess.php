<?php

declare(strict_types=1);

namespace App\Interface\Application\Security\Model;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\Access;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RequiresAccess
{
    public function __construct(
        private Action $action,
        private Resource $resource,
    ) {
    }

    public function access(): Access
    {
        return new Access($this->action, $this->resource);
    }
}
