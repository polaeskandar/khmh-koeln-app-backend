<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Response;

use App\Workflow\Organization\Model\ServiceAbilities;
use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StageSummary;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class ServiceResponse implements JsonSerializable
{
    public function __construct(
        private ServiceOverview $overview,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string|null,
     *     tone: int,
     *     archived: bool,
     *     archivedAt: string|null,
     *     stages: list<array{
     *         id: string,
     *         name: string,
     *         position: int,
     *         archived: bool,
     *         can: array{manageMembers: bool},
     *     }>,
     *     can: ServiceAbilities,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $service = $this->overview->service;

        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'tone' => $service->tone,
            'archived' => $service->archivedAt !== null,
            'archivedAt' => $service->archivedAt,
            'stages' => new ArrayCollection($this->overview->stages)->map($this->stageOf(...))->getValues(),
            'can' => $this->overview->can,
        ];
    }

    /**
     * @return array{id: string, name: string, position: int, archived: bool, can: array{manageMembers: bool}}
     */
    private function stageOf(StageSummary $stage): array
    {
        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'position' => $stage->position,
            'archived' => $stage->archived,
            'can' => ['manageMembers' => $this->overview->can->manageMembers],
        ];
    }
}
