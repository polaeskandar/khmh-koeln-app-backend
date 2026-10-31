<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Embeddable]
final readonly class Stamp
{
    public function __construct(
        #[ORM\Column(name: 'at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $at,
        #[ORM\Column(name: 'by', type: UuidType::NAME, nullable: true)]
        public ?Uuid $by,
    ) {
    }

    public static function none(): self
    {
        return new self(at: null, by: null);
    }
}
