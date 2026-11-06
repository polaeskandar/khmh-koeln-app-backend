<?php

declare(strict_types=1);

namespace App\Interface\Api\ParentPortal\Response;

use App\Workflow\ParentPortal\Model\ChildSummary;
use JsonSerializable;
use OpenApi\Attributes as OA;
use Override;

#[OA\Schema(required: ['id', 'name', 'fullName', 'initials', 'stage', 'service', 'toDo', 'done'], properties: [
    new OA\Property(property: 'id', type: 'string'),
    new OA\Property(property: 'name', type: 'string'),
    new OA\Property(property: 'fullName', type: 'string'),
    new OA\Property(property: 'initials', type: 'string'),
    new OA\Property(property: 'stage', type: 'string', nullable: true),
    new OA\Property(property: 'service', type: 'string', nullable: true),
    new OA\Property(property: 'toDo', type: 'integer'),
    new OA\Property(property: 'done', type: 'integer'),
])]
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
