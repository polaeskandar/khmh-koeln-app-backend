<?php

declare(strict_types=1);

namespace App\Interface\Api\ParentPortal\Response;

use App\Workflow\ParentPortal\Model\ChildSummary;
use JsonSerializable;
use Override;

final readonly class ChildSummaryResponse implements JsonSerializable
{
    public function __construct(
        private ChildSummary $child,
    ) {
    }

    /**
     * @return array<string, string|int|null>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $child = $this->child;
        $name = $child->name;

        return [
            'id' => $name->id,
            'name' => $name->name,
            'fullName' => $name->fullName,
            'initials' => $name->initials,
            'stage' => $child->group?->stage?->name,
            'service' => $child->group?->service->name,
            'toDo' => $child->toDo,
            'done' => $child->done,
        ];
    }
}
