<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\DataRequestDetail;
use App\Workflow\Privacy\Model\ErasureInventory;
use App\Workflow\Privacy\Model\RequestAccount;
use JsonSerializable;
use Override;

final readonly class DataRequestDetailResponse implements JsonSerializable
{
    public function __construct(
        private DataRequestDetail $detail,
        private ?ErasureInventory $inventory,
    ) {
    }

    /**
     * @return array{
     *     today: string,
     *     request: DataRequestRowResponse,
     *     member: RequestMemberResponse|null,
     *     account: RequestAccount|null,
     *     can: array{erase: bool},
     *     inventory: ErasureInventory|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'today' => $this->detail->today,
            'request' => new DataRequestRowResponse($this->detail->request),
            'member' => RequestMemberResponse::of($this->detail->member),
            'account' => $this->detail->account,
            'can' => ['erase' => $this->inventory !== null],
            'inventory' => $this->inventory,
        ];
    }
}
