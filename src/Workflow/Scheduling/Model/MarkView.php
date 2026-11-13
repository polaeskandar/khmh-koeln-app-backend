<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Context\Scheduling\Domain\Entity\Attendance;
use App\Workflow\Identity\Model\AccountReference;
use DateTimeImmutable;

final readonly class MarkView
{
    public function __construct(
        public string $id,
        public string $sessionId,
        public string $personId,
        public DateTimeImmutable $recordedAt,
        public ?AccountReference $recordedBy,
    ) {
    }

    public static function of(Attendance $mark, NamedAccounts $accounts): self
    {
        $recorder = $accounts->find($mark->recordedBy());

        return new self(
            $mark->id()->toRfc4122(),
            $mark->sessionId()->toRfc4122(),
            $mark->personId()->toRfc4122(),
            $mark->recordedAt(),
            $recorder === null ? null : AccountReference::of($recorder, $accounts),
        );
    }
}
