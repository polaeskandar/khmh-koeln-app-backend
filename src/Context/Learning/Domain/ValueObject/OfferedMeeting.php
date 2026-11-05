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
        $unchanged = $current?->equals($this->link) ?? false;

        return $this->canceled && !$unchanged;
    }
}
