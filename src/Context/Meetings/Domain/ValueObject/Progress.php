<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Progress
{
    private function __construct(
        #[ORM\Column(name: 'status', length: 9, enumType: SessionStatus::class)]
        public SessionStatus $status,
        #[ORM\Column(name: 'cancel_reason', length: 200, nullable: true)]
        public ?string $cancelReason,
        #[ORM\Embedded(Stamp::class, columnPrefix: 'opened_')]
        public Stamp $opened,
        #[ORM\Embedded(Stamp::class, columnPrefix: 'closed_')]
        public Stamp $closed,
    ) {
    }

    public static function scheduled(): self
    {
        return new self(SessionStatus::Scheduled, cancelReason: null, opened: Stamp::none(), closed: Stamp::none());
    }
}
