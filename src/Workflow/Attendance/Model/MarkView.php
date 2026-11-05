<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Workflow\Identity\Model\AccountReference;

final readonly class MarkView
{
    public function __construct(
        public string $id,
        public string $sessionId,
        public string $personId,
        public string $recordedAt,
        public ?AccountReference $recordedBy,
    ) {
    }

    public static function of(Attendance $mark, AttendanceMarks $marks, AccountLookup $accounts): self
    {
        $recordedBy = $mark->recordedBy();
        $recorder = $recordedBy === null ? null : $accounts->account($recordedBy);

        return new self(
            $mark->id()->toRfc4122(),
            $mark->sessionId()->toRfc4122(),
            $mark->personId()->toRfc4122(),
            $marks->recordedAtOf($mark),
            $recorder === null ? null : AccountReference::of($recorder),
        );
    }
}
