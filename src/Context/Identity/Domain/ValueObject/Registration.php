<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Registration
{
    private function __construct(
        #[ORM\Column(name: 'privacy_accepted_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $privacyAcceptedAt,
        #[ORM\Column(name: 'privacy_version', length: 20, nullable: true)]
        public ?string $privacyVersion,
    ) {
    }

    public static function none(): self
    {
        return new self(privacyAcceptedAt: null, privacyVersion: null);
    }
}
