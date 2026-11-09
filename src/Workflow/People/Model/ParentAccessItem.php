<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\Enum\GuardianKind;

final readonly class ParentAccessItem
{
    public function __construct(
        public string $id,
        public GuardianKind $relationship,
        public ParentAccountView $parent,
        public DirectoryEntry $child,
        public AccessApproval $approval,
    ) {
    }
}
