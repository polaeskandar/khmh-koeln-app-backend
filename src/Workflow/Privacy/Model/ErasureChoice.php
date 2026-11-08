<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Platform\Privacy\Model\FoundNote;

final readonly class ErasureChoice
{
    public function __construct(
        public bool $clearNotes,
    ) {
    }

    /**
     * @param list<FoundNote> $mentions
     *
     * @return list<FoundNote>
     */
    public function notesToClear(array $mentions): array
    {
        return $this->clearNotes ? $mentions : [];
    }
}
