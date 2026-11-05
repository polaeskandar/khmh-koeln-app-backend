<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class CustomDetails
{
    public function __construct(
        #[ORM\Column(name: 'title', length: 120, nullable: true)]
        public ?string $title = null,
        #[ORM\Column(name: 'location', length: 120, nullable: true)]
        public ?string $location = null,
    ) {
    }

    public function over(MeetingDetails $planned): MeetingDetails
    {
        return $this->title === null
            ? $planned
            : new MeetingDetails($this->title, $planned->description, $this->location);
    }

    public function differsFrom(MeetingDetails $current): bool
    {
        return $current->name !== $this->title || $current->location !== $this->location;
    }
}
