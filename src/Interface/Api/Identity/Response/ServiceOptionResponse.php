<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\ServiceOption;

final readonly class ServiceOptionResponse
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    public static function of(ServiceOption $option): self
    {
        return new self($option->id, $option->name);
    }
}
