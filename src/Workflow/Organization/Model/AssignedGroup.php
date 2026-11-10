<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\StaffAssignment;

final readonly class AssignedGroup
{
    public function __construct(
        public ServiceReference $service,
        public ?StageReference $stage,
    ) {
    }

    public static function of(StaffAssignment $assignment): self
    {
        $stage = $assignment->stage();

        return new self(
            ServiceReference::of($assignment->service()),
            $stage === null ? null : StageReference::of($stage),
        );
    }
}
