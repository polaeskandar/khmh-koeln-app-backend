<?php

declare(strict_types=1);

namespace App\Platform\Privacy\Model;

use Symfony\Component\Uid\Uuid;

final readonly class FoundNote
{
    public function __construct(
        public NoteRef $ref,
        public string $text,
        public string $date,
        public ?string $meeting,
        public ?Uuid $stageId,
    ) {
    }
}
