<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class LessonText
{
    public function __construct(
        #[ORM\Column(name: 'title', length: 160)]
        public string $title,
        #[ORM\Column(name: 'summary', length: 1000, nullable: true)]
        public ?string $summary = null,
        #[ORM\Column(name: 'passage', length: 200, nullable: true)]
        public ?string $passage = null,
        #[ORM\Column(name: 'memory_verse', length: 500, nullable: true)]
        public ?string $memoryVerse = null,
    ) {
    }
}
