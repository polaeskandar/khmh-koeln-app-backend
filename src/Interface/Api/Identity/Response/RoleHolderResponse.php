<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\RoleHolder;

final readonly class RoleHolderResponse
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $email,
        public bool $disabled,
    ) {
    }

    public static function of(RoleHolder $holder): self
    {
        return new self($holder->id, $holder->displayName, $holder->email, $holder->disabled);
    }
}
