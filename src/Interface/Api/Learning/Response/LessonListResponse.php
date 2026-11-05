<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Learning\Model\LessonList;
use App\Workflow\Learning\Model\LessonRow;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class LessonListResponse implements JsonSerializable
{
    public function __construct(
        private LessonList $list,
    ) {
    }

    /**
     * @return array{
     *     service: ServiceReference,
     *     stage: StageSummary,
     *     archived: bool,
     *     today: string,
     *     tabs: array<string, int>,
     *     servants: list<AccountReference>,
     *     items: list<LessonRowResponse>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $list = $this->list;
        $rowOf = static fn (LessonRow $row): LessonRowResponse => new LessonRowResponse($row);

        return [
            'service' => $list->stage->service,
            'stage' => $list->stage->stage,
            'archived' => $list->stage->archived,
            'today' => $list->today,
            'tabs' => $list->tabs,
            'servants' => $list->servants,
            'items' => new ArrayCollection($list->items)->map($rowOf)->getValues(),
        ];
    }
}
