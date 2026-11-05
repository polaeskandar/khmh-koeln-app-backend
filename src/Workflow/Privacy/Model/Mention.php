<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class Mention
{
    public function __construct(
        public string $id,
        public string $where,
        public string $text,
        public ?string $date,
        public ?string $group,
    ) {
    }
}
