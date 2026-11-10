<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Organization\Application\Service\AssignmentChanges;
use App\Context\Organization\Application\Service\StaffAssignments;
use App\Library\Problem\Model\Ids;
use App\Platform\Access\Model\Group;

final readonly class RemoveAssignmentWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StaffAssignments $assignments,
        private AssignmentChanges $changes,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->requireAssigning();

        $assignment = $this->assignments->get(Ids::toUuid($id, notFound: 'assignment'));
        $this->access->requireAssigningIn(new Group($assignment->service()->id(), $assignment->stage()?->id()));

        $this->changes->remove($assignment);
    }
}
