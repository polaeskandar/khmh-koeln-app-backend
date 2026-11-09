<?php

declare(strict_types=1);

namespace App\Platform\Privacy\Model;

final readonly class NoteReference
{
    public function __construct(
        public string $where,
        public string $id,
    ) {
    }
}
