<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Embeddable]
final readonly class Actor
{
    private function __construct(
        #[ORM\Column(name: 'actor_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $accountId,
        #[ORM\Column(name: 'by_generator')]
        public bool $isGenerator,
    ) {
    }

    public static function account(Uuid $accountId): self
    {
        return new self($accountId, isGenerator: false);
    }

    public static function generator(): self
    {
        return new self(accountId: null, isGenerator: true);
    }
}
