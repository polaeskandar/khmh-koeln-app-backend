<?php

declare(strict_types=1);

namespace App\Interface\Api\ParentPortal\Response;

use App\Workflow\ParentPortal\Model\ChildHomeworkDetail;
use JsonSerializable;
use Override;

final readonly class ChildHomeworkDetailResponse implements JsonSerializable
{
    public function __construct(
        private ChildHomeworkDetail $detail,
    ) {
    }

    /**
     * @return array{
     *     today: string,
     *     child: array{id: string, name: string},
     *     task: array<string, string|bool|list<array{label: string, url: string|null}>|null>,
     *     lesson: array{title: string, teachingDate: string, passage: string|null, memoryVerse: string|null},
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $detail = $this->detail;

        return [
            'today' => $detail->today,
            'child' => ['id' => $detail->child->id, 'name' => $detail->child->name],
            ...new HomeworkTaskResponse($detail->item, $detail->text, $detail->lesson)->jsonSerialize(),
        ];
    }
}
