<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Enum\SessionStatus;
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

    public static function of(Session $session, NamedAccounts $accounts): self
    {
        $closer = $accounts->find($session->progress()->closed->by);

        return self::referencing($session, $closer === null ? null : AccountReference::of($closer, $accounts));
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
