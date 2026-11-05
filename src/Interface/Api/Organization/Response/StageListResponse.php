<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Response;

use App\Workflow\Organization\Model\ArchivedStageEntry;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageEntry;
use App\Workflow\Organization\Model\StageList;

final readonly class StageListResponse
{
    /**
     * @param list<StageEntry> $stages
     * @param list<ArchivedStageEntry> $archivedStages
     * @param array{manageStructure: bool} $can
     */
    public function __construct(
        public ServiceReference $service,
        public array $stages,
        public array $archivedStages,
        public array $can,
    ) {
    }

    public static function of(StageList $list): self
    {
        return new self($list->service, $list->active, $list->archived, [
            'manageStructure' => $list->canManageStructure,
        ]);
    }
}
