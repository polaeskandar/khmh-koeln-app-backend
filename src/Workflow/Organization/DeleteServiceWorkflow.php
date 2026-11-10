<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Meetings\Application\Service\ScheduleRemovals;
use App\Context\Organization\Application\Service\StructureRemovals;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\ValueObject\StructureUse;
use App\Context\People\Application\Service\Memberships;
use App\Workflow\Organization\Model\StructureGroup;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureRemovals $removals,
        private ScheduleRemovals $schedules,
        private StageLessons $lessons,
        private Memberships $memberships,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Services, refusal: 'services.adminOnlyDelete');

        $this->access->requireIn(
            Action::Delete,
            Resource::Services,
            StructureGroup::ofServiceId($id),
            missing: 'service',
        );

        $this->removals->removeService($id, release: $this->released(...));
    }

    /**
     * @param list<Uuid> $stageIds
     */
    private function released(Service $service, array $stageIds): StructureUse
    {
        return new StructureUse(
            sessions: $this->schedules->releaseOfService($service->id()),
            lessons: $this->lessons->countIn($stageIds),
            members: $this->memberships->countInService($service->id()),
        );
    }
}
