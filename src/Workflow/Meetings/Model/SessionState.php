<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Workflow\Identity\Model\AccountReference;
use DateTimeImmutable;

final readonly class SessionState
{
    public function __construct(
        public string $id,
        public SessionStatus $status,
        public ?DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $closedAt,
        public ?AccountReference $closedBy,
    ) {
    }

    public static function of(Session $session, ?User $closer): self
    {
        return self::referencing($session, $closer === null ? null : AccountReference::of($closer));
    }

    public static function referencing(Session $session, ?AccountReference $closedBy): self
    {
        $progress = $session->progress();

        return new self(
            id: $session->id()->toRfc4122(),
            status: $progress->status,
            openedAt: $progress->opened->at,
            closedAt: $progress->closed->at,
            closedBy: $closedBy,
        );
    }
}
