<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class ApprovalChoice
{
    private const string PARENT = 'parent';

    /**
     * @param list<string> $roleIds
     * @param list<string> $childIds
     * @param list<array{serviceId: ?string, stageId: ?string}> $assignments
     */
    public function __construct(
        public string $as,
        public array $roleIds,
        public array $childIds,
        public array $assignments,
    ) {
    }

    public function isParent(): bool
    {
        return $this->as === self::PARENT;
    }
}
