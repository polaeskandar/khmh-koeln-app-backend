<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Response;

use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageDetails;
use JsonSerializable;
use Override;

final readonly class StageResponse implements JsonSerializable
{
    public function __construct(
        private StageDetails $details,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string|null,
     *     position: int,
     *     archived: bool,
     *     service: ServiceReference,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $stage = $this->details->stage;

        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'description' => $stage->description,
            'position' => $stage->position,
            'archived' => $this->details->archived,
            'service' => $this->details->service,
        ];
    }
}
