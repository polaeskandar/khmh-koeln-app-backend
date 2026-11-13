<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Organization\Application\Service\StructureRemovals;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\ValueObject\StructureUse;
use App\Context\People\Domain\Query\MembershipQueryInterface;
use App\Context\Scheduling\Application\Service\ScheduleRemovals;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class DeleteStageWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureRemovals $removals,
        private ScheduleRemovals $schedules,
        private StageLessons $lessons,
        private MembershipQueryInterface $memberships,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Stages);

        $this->removals->removeStage(Ids::toUuid($id, notFound: 'stage'), release: $this->released(...));
    }

    private function released(Stage $stage): StructureUse
    {
        $this->access->requireIn(Action::Delete, Resource::Stages, StructureGroup::ofStage($stage), missing: 'stage');

        return new StructureUse(
            sessions: $this->schedules->releaseOfStage($stage->id()),
            lessons: $this->lessons->countIn([$stage->id()]),
            members: $this->memberships->countInStage($stage->id()),
        );
    }
}
