<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\AudienceRow;
use App\Workflow\Learning\Model\HomeworkProgress;
use App\Workflow\Learning\Model\LessonOnDate;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class HomeworkProgressResponse implements JsonSerializable
{
    public function __construct(
        private HomeworkProgress $progress,
    ) {
    }

    /**
     * @return array{
     *     today: string,
     *     service: ServiceReference,
     *     stage: StageSummary,
     *     homework: array{
     *         id: string,
     *         title: string,
     *         status: string,
     *         dueDate: string|null,
     *         publishedAt: string|null,
     *         lesson: LessonOnDate,
     *         counts: AudienceCountsResponse|null,
     *         can: HomeworkAbilitiesResponse,
     *         instructions: string|null,
     *         resources: list<array{label: string, url: string|null}>,
     *     },
     *     lesson: LessonOnDate,
     *     counts: AudienceCountsResponse|null,
     *     rows: list<AudienceRowResponse>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $progress = $this->progress;
        $item = $progress->item;
        $rowOf = static fn (AudienceRow $row): AudienceRowResponse => new AudienceRowResponse($row);

        return [
            'today' => $progress->today,
            'service' => $progress->stage->service,
            'stage' => $progress->stage->stage,
            'homework' => [
                ...new HomeworkItemResponse($item)->jsonSerialize(),
                'instructions' => $progress->text->instructions,
                'resources' => $progress->text->resources,
            ],
            'lesson' => $item->lesson,
            'counts' => $item->counts === null ? null : new AudienceCountsResponse($item->counts),
            'rows' => new ArrayCollection($progress->rows)->map($rowOf)->getValues(),
        ];
    }
}
