<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Response;

use App\Workflow\Organization\Model\ArchivedStageEntry;
use App\Workflow\Organization\Model\ArchiveImpact;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageEntry;
use App\Workflow\Organization\Model\StageList;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class StageListResponse implements JsonSerializable
{
    public function __construct(
        private StageList $list,
    ) {
    }

    public static function of(StageList $list): self
    {
        return new self($list);
    }

    /**
     * @return array{
     *     service: ServiceReference,
     *     stages: list<array<string, string|int|ArchiveImpact|null>>,
     *     archivedStages: list<array<string, string|ArchiveImpact|null>>,
     *     can: array{manageStructure: bool},
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'service' => $this->list->service,
            'stages' => new ArrayCollection($this->list->active)->map($this->activeRow(...))->getValues(),
            'archivedStages' => new ArrayCollection($this->list->archived)->map($this->archivedRow(...))->getValues(),
            'can' => ['manageStructure' => $this->list->canManageStructure],
        ];
    }

    /**
     * @return array<string, string|int|ArchiveImpact|null>
     */
    private function activeRow(StageEntry $stage): array
    {
        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'description' => $stage->description,
            'position' => $stage->position,
            'memberCount' => $stage->memberCount,
            'impact' => $this->impactOf($stage->id),
        ];
    }

    /**
     * @return array<string, string|ArchiveImpact|null>
     */
    private function archivedRow(ArchivedStageEntry $stage): array
    {
        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'archivedAt' => $stage->archivedAt,
            'impact' => $this->impactOf($stage->id),
        ];
    }

    private function impactOf(string $stageId): ArchiveImpact
    {
        return $this->list->impacts->get($stageId) ?? ArchiveImpact::of(null);
    }
}
