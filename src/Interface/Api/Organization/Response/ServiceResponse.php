<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Response;

use App\Workflow\Organization\Model\ServiceOverview;
use App\Workflow\Organization\Model\StageSummary;
use DateTimeImmutable;
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
     *     archivedAt: DateTimeImmutable|null,
     *     memberCount: int,
     *     serviceLevelMemberCount: int,
     *     stages: list<array{id: string, name: string, position: int}>,
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
            'memberCount' => $this->overview->members->members,
            'serviceLevelMemberCount' => $this->overview->members->serviceLevel,
            'stages' => new ArrayCollection($this->overview->stages)->map(self::stageOf(...))->getValues(),
        ];
    }

    /**
     * @return array{id: string, name: string, position: int}
     */
    private static function stageOf(StageSummary $stage): array
    {
        return ['id' => $stage->id, 'name' => $stage->name, 'position' => $stage->position];
    }
}
