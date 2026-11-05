<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance\Response;

use App\Interface\Api\Meetings\Response\SessionResponse;
use App\Workflow\Attendance\Model\BoardSession;
use JsonSerializable;
use Override;

final readonly class BoardSessionResponse implements JsonSerializable
{
    public function __construct(
        private BoardSession $session,
    ) {
    }

    /**
     * @return array<string, scalar|object|array<string, bool>|null>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            ...new SessionResponse($this->session->view)->jsonSerialize(),
            'openedAt' => $this->session->openedAt,
            'closedAt' => $this->session->closedAt,
            'closedBy' => $this->session->closedBy,
        ];
    }
}
