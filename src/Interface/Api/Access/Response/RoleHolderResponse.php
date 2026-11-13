<?php

declare(strict_types=1);

namespace App\Interface\Api\Access\Response;

use App\Workflow\Access\Model\RoleHolder;

final readonly class RoleHolderResponse
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $username,
        public bool $disabled,
    ) {
    }

    public static function of(RoleHolder $holder): self
    {
        return new self($holder->id, $holder->displayName, $holder->username, $holder->disabled);
    }
}
