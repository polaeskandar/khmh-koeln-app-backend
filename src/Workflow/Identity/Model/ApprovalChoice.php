<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class ApprovalChoice
{
    private const string PARENT = 'parent';

    /**
     * @param list<string> $roleIds
     * @param list<string> $childIds
     */
    public function __construct(
        public string $as,
        public array $roleIds,
        public array $childIds,
    ) {
    }

    public function isParent(): bool
    {
        return $this->as === self::PARENT;
    }
}
