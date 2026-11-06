<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ChurchServices;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Workflow\Organization\Model\ServiceList;
use App\Workflow\Organization\Model\ServiceOverview;

final readonly class ListServicesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ChurchServices $services,
        private ServiceStages $stages,
    ) {
    }

    public function __invoke(string $status): ServiceList
    {
        $this->access->require(Action::Read, Resource::Services);

        $services = $this->services->listed($status);
        $stages = $this->stages->activeIn($services);

        return new ServiceList(
            $services->map(static fn (Service $service): ServiceOverview => ServiceOverview::of(
                $service,
                $stages->filter(static fn (Stage $stage): bool => $stage->service() === $service),
            ))->getValues(),
        );
    }
}
