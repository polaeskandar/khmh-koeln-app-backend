<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\OwnErasureStatus;

final readonly class OwnErasureStatusResponse
{
    public function __construct(
        public ?OwnErasureRequestResponse $erasureRequested,
    ) {
    }

    public static function of(OwnErasureStatus $status): self
    {
        $requested = $status->erasureRequested;

        return new self($requested === null ? null : OwnErasureRequestResponse::of($requested));
    }
}
