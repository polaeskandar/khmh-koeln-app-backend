<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class MeetingDetails
{
    public function __construct(
        #[ORM\Column(name: 'name', length: 120)]
        public string $name,
        #[ORM\Column(name: 'description', length: 500, nullable: true)]
        public ?string $description,
        #[ORM\Column(name: 'location', length: 120, nullable: true)]
        public ?string $location,
    ) {
    }
}
