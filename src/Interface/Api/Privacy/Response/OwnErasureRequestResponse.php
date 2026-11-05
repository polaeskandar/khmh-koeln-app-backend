<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\OwnErasureRequest;
use DateTimeImmutable;

final readonly class OwnErasureRequestResponse
{
    public function __construct(
        public DateTimeImmutable $receivedAt,
        public string $dueOn,
    ) {
    }

    public static function of(OwnErasureRequest $request): self
    {
        return new self($request->receivedAt, $request->dueOn);
    }
}
