<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;

final readonly class SessionProgress
{
    public function __construct(
        public string $status,
        public ?string $cancelReason,
    ) {
    }

    public static function of(Session $session, SessionAbilities $can): self
    {
        $progress = $session->progress();

        return new self(
            status: $progress->status->value,
            cancelReason: $can->readContent ? $progress->cancelReason : null,
        );
    }
}
