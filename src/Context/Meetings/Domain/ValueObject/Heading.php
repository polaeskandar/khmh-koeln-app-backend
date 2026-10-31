<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Heading
{
    public function __construct(
        #[ORM\Column(name: 'title', length: 120)]
        public string $title,
        #[ORM\Column(name: 'location', length: 120, nullable: true)]
        public ?string $location,
    ) {
    }
}
