<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Response;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\People\Model\DirectoryEntry;
use App\Workflow\People\Model\ParentAccessItem;
use App\Workflow\People\Model\ParentAccountView;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class ParentAccessItemResponse implements JsonSerializable
{
    public function __construct(
        private ParentAccessItem $item,
    ) {
    }

    /**
     * @param list<ParentAccessItem> $items
     *
     * @return list<self>
     */
    public static function listOf(array $items): array
    {
        return new ArrayCollection($items)->map(
            static fn (ParentAccessItem $item): self => new self($item),
        )->getValues();
    }

    /**
     * @return array{
     *     id: string,
     *     relationship: string,
     *     parent: ParentAccountView,
     *     child: DirectoryEntry,
     *     approvedAt: DateTimeImmutable,
     *     approvedBy: AccountReference|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $item = $this->item;

        return [
            'id' => $item->id,
            'relationship' => $item->relationship,
            'parent' => $item->parent,
            'child' => $item->child,
            'approvedAt' => $item->approval->at,
            'approvedBy' => $item->approval->by,
        ];
    }
}
