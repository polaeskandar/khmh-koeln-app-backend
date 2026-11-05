<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

final readonly class ParentAccessItem
{
    public function __construct(
        public string $id,
        public string $relationship,
        public ParentAccountView $parent,
        public DirectoryEntry $child,
        public AccessApproval $approval,
    ) {
    }
}
