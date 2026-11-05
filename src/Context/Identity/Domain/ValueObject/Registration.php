<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Enum\RegisteredAs;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Registration
{
    private function __construct(
        #[ORM\Column(name: 'registered_as', length: 7, nullable: true, enumType: RegisteredAs::class)]
        public ?RegisteredAs $registeredAs,
        #[ORM\Column(name: 'registration_note', type: Types::TEXT, nullable: true)]
        public ?string $note,
        #[ORM\Column(name: 'privacy_accepted_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $privacyAcceptedAt,
        #[ORM\Column(name: 'privacy_version', length: 20, nullable: true)]
        public ?string $privacyVersion,
    ) {
    }

    public static function of(
        RegisteredAs $registeredAs,
        string $note,
        DateTimeImmutable $privacyAcceptedAt,
        string $privacyVersion,
    ): self {
        return new self($registeredAs, $note, $privacyAcceptedAt, $privacyVersion);
    }

    public function decided(): self
    {
        return new self(
            registeredAs: null,
            note: null,
            privacyAcceptedAt: $this->privacyAcceptedAt,
            privacyVersion: $this->privacyVersion,
        );
    }

    public static function none(): self
    {
        return new self(registeredAs: null, note: null, privacyAcceptedAt: null, privacyVersion: null);
    }
}
