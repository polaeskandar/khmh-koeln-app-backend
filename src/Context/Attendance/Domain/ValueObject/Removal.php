<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Embeddable]
final readonly class Removal
{
    private const int REASON_LENGTH = 200;

    private function __construct(
        #[ORM\Column(name: 'removed_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $at,
        #[ORM\Column(name: 'removed_by', type: UuidType::NAME, nullable: true)]
        public ?Uuid $by,
        #[ORM\Column(name: 'removal_reason', length: self::REASON_LENGTH, nullable: true)]
        public ?string $reason,
    ) {
    }

    public static function none(): self
    {
        return new self(at: null, by: null, reason: null);
    }

    public static function of(DateTimeImmutable $at, ?Uuid $by, ?string $reason): self
    {
        $clean = InputText::of($reason)->trimmed();

        if ($clean->length() > self::REASON_LENGTH) {
            throw InvalidInput::tooLong('reason', self::REASON_LENGTH);
        }

        return new self($at, $by, $clean->toStringOrNull());
    }

    public function happened(): bool
    {
        return $this->at !== null;
    }
}
