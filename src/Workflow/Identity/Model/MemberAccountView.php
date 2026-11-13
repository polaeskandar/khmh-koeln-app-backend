<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class MemberAccountView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $username,
        public bool $isParent,
        public bool $disabled,
    ) {
    }
}
