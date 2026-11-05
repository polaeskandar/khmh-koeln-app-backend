<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class RoleSummary
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
