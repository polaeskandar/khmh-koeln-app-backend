<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Response;

use App\Workflow\People\Model\PeoplePage;
use App\Workflow\People\Model\PersonRow;
use JsonSerializable;
use Override;

final readonly class PeoplePageResponse implements JsonSerializable
{
    public function __construct(
        private PeoplePage $page,
    ) {
    }

    /**
     * @return array{
     *     today: string,
     *     items: list<PersonRow>,
     *     page: int,
     *     pageSize: int,
     *     total: int,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $pages = $this->page->pages;

        return [
            'today' => $this->page->today,
            'items' => $this->page->items,
            'page' => $pages->page,
            'pageSize' => $pages->pageSize,
            'total' => $pages->total,
        ];
    }
}
