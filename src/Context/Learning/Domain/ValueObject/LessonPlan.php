<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class LessonPlan
{
    public function __construct(
        #[ORM\Column(name: 'objectives', length: 4000, nullable: true)]
        public ?string $objectives = null,
        #[ORM\Column(name: 'teaching_points', length: 4000, nullable: true)]
        public ?string $teachingPoints = null,
        #[ORM\Column(name: 'activities', length: 4000, nullable: true)]
        public ?string $activities = null,
        #[ORM\Column(name: 'notes', length: 4000, nullable: true)]
        public ?string $notes = null,
    ) {
    }
}
