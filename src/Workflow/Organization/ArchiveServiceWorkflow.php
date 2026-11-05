<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\SchedulePauses;
use App\Context\Organization\Application\Service\ServiceArchives;
use App\Context\Organization\Domain\Entity\Service;

final readonly class ArchiveServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceArchives $archives,
        private SchedulePauses $pauses,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Services, refusal: 'services.adminOnlyArchive');

        $this->archives->archive($id, pause: fn (Service $service): bool => $this->pauses->pauseService(
            $service->id(),
        ));
    }
}
