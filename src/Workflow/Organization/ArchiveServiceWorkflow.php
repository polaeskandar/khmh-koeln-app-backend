<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\SchedulePauses;
use App\Context\Organization\Application\Service\ServiceArchives;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceOverview;

final readonly class ArchiveServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceArchives $archives,
        private SchedulePauses $pauses,
        private ServiceStages $stages,
        private Rosters $rosters,
    ) {
    }

    public function __invoke(string $id): ServiceOverview
    {
        $this->access->require(Action::Delete, Resource::Services, refusal: 'services.adminOnlyArchive');

        $service = $this->archives->archive($id, pause: fn (Service $service): bool => $this->pauses->pauseService(
            $service->id(),
        ));

        return ServiceOverview::of(
            $service,
            $this->stages->activeOf($service),
            MemberCounts::in($this->rosters->serviceMembersToday([$service->id()]), $service),
        );
    }
}
