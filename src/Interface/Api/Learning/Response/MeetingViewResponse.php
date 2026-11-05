<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\MeetingView;
use JsonSerializable;
use Override;

final readonly class MeetingViewResponse implements JsonSerializable
{
    public function __construct(
        private MeetingView $view,
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['kind' => $this->view->kind, ...$this->view->members];
    }
}
