<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\SessionView;
use JsonSerializable;
use Override;

final readonly class AgendaSessionResponse implements JsonSerializable
{
    public function __construct(
        private SessionView $view,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['kind' => 'session', ...new SessionResponse($this->view)->jsonSerialize()];
    }
}
