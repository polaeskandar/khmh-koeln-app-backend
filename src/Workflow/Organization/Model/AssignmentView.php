<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\StaffAssignment;

final readonly class AssignmentView
{
    public function __construct(
        public string $id,
        public ServiceReference $service,
        public ?StageReference $stage,
    ) {
    }

    public static function of(StaffAssignment $assignment): self
    {
        $group = AssignedGroup::of($assignment);

        return new self($assignment->id()->toRfc4122(), $group->service, $group->stage);
    }
}
