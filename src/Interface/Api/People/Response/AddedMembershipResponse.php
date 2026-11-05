<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Response;

use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
use App\Workflow\People\Model\AddedMembership;
use JsonSerializable;
use Override;

final readonly class AddedMembershipResponse implements JsonSerializable
{
    public function __construct(
        private AddedMembership $membership,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     personId: string,
     *     service: ServiceReference,
     *     stage: StageSummary|null,
     *     startsOn: string,
     *     endsOn: null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->membership->id,
            'personId' => $this->membership->personId,
            'service' => $this->membership->service,
            'stage' => $this->membership->stage,
            'startsOn' => $this->membership->startsOn,
            'endsOn' => null,
        ];
    }
}
