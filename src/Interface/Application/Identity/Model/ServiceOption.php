<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\Model;

final readonly class ServiceOption
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
