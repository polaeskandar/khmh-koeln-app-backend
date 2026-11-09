<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\HomeworkItem;
use App\Workflow\Learning\Model\HomeworkList;
use App\Workflow\Learning\Model\LessonOnDate;
use App\Workflow\Organization\Model\StageSummary;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class HomeworkListResponse implements JsonSerializable
{
    public function __construct(
        private HomeworkList $list,
    ) {
    }

    /**
     * @return array{
     *     today: string,
     *     stage: StageSummary,
     *     archived: bool,
     *     tabs: array{published: int, draft: int, archived: int},
     *     lessons: list<LessonOnDate>,
     *     defaultLessonId: string|null,
     *     audienceSize: int,
     *     items: list<HomeworkItemResponse>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $list = $this->list;
        $facts = $list->facts;
        $itemOf = static fn (HomeworkItem $item): HomeworkItemResponse => new HomeworkItemResponse($item);

        return [
            'today' => $facts->today,
            'stage' => $list->stage,
            'archived' => $facts->archived,
            'tabs' => $facts->tabs,
            'lessons' => $list->lessons->lessons,
            'defaultLessonId' => $list->lessons->defaultLessonId,
            'audienceSize' => $facts->audienceSize,
            'items' => new ArrayCollection($list->items)->map($itemOf)->getValues(),
        ];
    }
}
