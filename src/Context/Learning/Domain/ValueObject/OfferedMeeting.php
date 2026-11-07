<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class OfferedMeeting
{
    public function __construct(
        public MeetingLink $link,
        public ?Uuid $stageId,
        public string $name,
        public bool $scheduled,
        public bool $canceled,
    ) {
    }

    public function belongsTo(Uuid $stageId): bool
    {
        return $this->stageId?->equals($stageId) ?? false;
    }

    public function isNewlyCanceled(?MeetingLink $current): bool
    {
        return $this->canceled && !$this->keeps($current);
    }

    public function isNewlyUnscheduled(?MeetingLink $current): bool
    {
        return !$this->scheduled && !$this->keeps($current);
    }

    private function keeps(?MeetingLink $current): bool
    {
        return $current?->equals($this->link) ?? false;
    }
}
