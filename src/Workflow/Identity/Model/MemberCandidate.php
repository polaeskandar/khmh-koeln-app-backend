<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Workflow\People\Model\DirectoryEntry;

final readonly class MemberCandidate
{
    public function __construct(
        public DirectoryEntry $entry,
        public bool $hasAccount,
    ) {
    }
}
