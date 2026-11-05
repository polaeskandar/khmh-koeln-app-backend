<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ChurchServices;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceAbilities;
use App\Workflow\Organization\Model\ServiceInsights;
use App\Workflow\Organization\Model\ServiceOverview;

final readonly class CreateServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ChurchServices $services,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $name, ?string $description): ServiceOverview
    {
        $this->access->require(Action::Create, Resource::Services, refusal: 'services.adminOnlyCreate');

        $service = $this->services->create($name, $description);

        return ServiceOverview::of(
            $service,
            $this->services->activeStagesOf($service),
            MemberCounts::none(),
            ServiceAbilities::grantedBy($this->access),
            ServiceInsights::ofNewService($this->accounts),
        );
    }
}
