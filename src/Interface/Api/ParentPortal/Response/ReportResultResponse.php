<?php

declare(strict_types=1);

namespace App\Interface\Api\ParentPortal\Response;

use App\Workflow\ParentPortal\Model\ReportResult;
use JsonSerializable;
use Override;

final readonly class ReportResultResponse implements JsonSerializable
{
    public function __construct(
        private ReportResult $result,
    ) {
    }

    /**
     * @return array{done: true, completedAt: string, completedBy: string|null, completedByYou: bool}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'done' => true,
            'completedAt' => $this->result->completedAt,
            'completedBy' => $this->result->completedBy,
            'completedByYou' => $this->result->completedByYou,
        ];
    }
}
