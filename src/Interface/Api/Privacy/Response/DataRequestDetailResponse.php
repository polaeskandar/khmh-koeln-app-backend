<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\CopyLinkStatus;
use App\Workflow\Privacy\Model\DataRequestDetail;
use App\Workflow\Privacy\Model\RequestAccount;

final readonly class DataRequestDetailResponse
{
    public function __construct(
        public string $today,
        public DataRequestRowResponse $request,
        public ?RequestMemberResponse $member,
        public ?RequestAccount $account,
        public ?CopyLinkStatus $copyLink,
    ) {
    }

    public static function of(DataRequestDetail $detail): self
    {
        return new self(
            $detail->today,
            new DataRequestRowResponse($detail->request),
            RequestMemberResponse::of($detail->member),
            $detail->account,
            $detail->copyLink,
        );
    }
}
