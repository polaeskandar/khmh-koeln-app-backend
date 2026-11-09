<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\StageLessons;
use App\Context\Meetings\Application\Service\ScheduleDeletions;
use App\Context\Organization\Application\Service\StructureDeletions;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\ValueObject\StructureUse;
use App\Context\People\Application\Service\Memberships;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StructureDeletions $deletions,
        private ScheduleDeletions $schedules,
        private StageLessons $lessons,
        private Memberships $memberships,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Services, refusal: 'services.adminOnlyDelete');

        $this->deletions->deleteService($id, release: $this->released(...));
    }

    /**
     * @param list<Uuid> $stageIds
     */
    private function released(Service $service, array $stageIds): StructureUse
    {
        return new StructureUse(
            sessions: $this->schedules->deleteSchedulesOfService($service->id()),
            lessons: $this->lessons->countIn($stageIds),
            members: $this->memberships->countInService($service->id()),
        );
    }
}
