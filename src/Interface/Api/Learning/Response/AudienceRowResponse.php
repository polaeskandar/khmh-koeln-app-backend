<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Learning\Model\AudienceRow;
use App\Workflow\People\Model\PersonReference;
use JsonSerializable;
use Override;

final readonly class AudienceRowResponse implements JsonSerializable
{
    public function __construct(
        private AudienceRow $row,
    ) {
    }

    /**
     * @return array{
     *     person: PersonReference,
     *     inStage: bool,
     *     done: bool,
     *     completedAt: string|null,
     *     completedBy: AccountReference|null,
     *     completedAfterDueDate: bool,
     *     parentAccounts: list<AccountReference>,
     *     ownAccount: bool,
     *     reportedBySelf: bool,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $row = $this->row;
        $report = $row->report;

        return [
            'person' => $row->person,
            'inStage' => $row->inStage,
            'done' => $report !== null,
            'completedAt' => $report?->completedAt,
            'completedBy' => $report?->completedBy,
            'completedAfterDueDate' => $report->completedAfterDueDate ?? false,
            'parentAccounts' => $row->parentAccounts,
            'ownAccount' => $row->ownAccount,
            'reportedBySelf' => $report->bySelf ?? false,
        ];
    }
}
