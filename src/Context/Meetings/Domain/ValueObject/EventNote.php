<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class EventNote
{
    private function __construct(
        #[ORM\Column(name: 'note', length: 200, nullable: true)]
        public ?string $note,
        #[ORM\Column(name: 'previous_starts_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $previousStartsAt,
    ) {
    }

    public static function none(): self
    {
        return new self(note: null, previousStartsAt: null);
    }

    public static function reason(string $note): self
    {
        return new self($note, previousStartsAt: null);
    }

    public static function movedFrom(DateTimeImmutable $previousStartsAt): self
    {
        return new self(note: null, previousStartsAt: $previousStartsAt);
    }
}
