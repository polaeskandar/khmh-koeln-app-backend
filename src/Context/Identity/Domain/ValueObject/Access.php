<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class Access
{
    public const string ATTRIBUTE = 'access';

    public function __construct(
        private Action $action,
        private Resource $resource,
    ) {
    }

    public function isAllowedFor(User $user): bool
    {
        return $user->status()->isActive() && $user->can($this->action, $this->resource);
    }
}
