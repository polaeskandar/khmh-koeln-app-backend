<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Meetings\Domain\Entity\Session;
use App\Workflow\Identity\Model\AccountReference;
use DateTimeInterface;

final readonly class SessionState
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $openedAt,
        public ?string $closedAt,
        public ?AccountReference $closedBy,
    ) {
    }

    public static function of(Session $session, AccountLookup $accounts): self
    {
        $progress = $session->progress();
        $closedBy = $progress->closed->by;
        $closer = $closedBy === null ? null : $accounts->account($closedBy);

        return new self(
            id: $session->id()->toRfc4122(),
            status: $progress->status->value,
            openedAt: $progress->opened->at?->format(DateTimeInterface::ATOM),
            closedAt: $progress->closed->at?->format(DateTimeInterface::ATOM),
            closedBy: $closer === null ? null : AccountReference::of($closer),
        );
    }
}
