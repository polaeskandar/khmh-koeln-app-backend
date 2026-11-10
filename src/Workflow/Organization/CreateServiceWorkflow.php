<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ServiceWrites;
use App\Workflow\Organization\Model\MemberCounts;
use App\Workflow\Organization\Model\ServiceOverview;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class CreateServiceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ServiceWrites $services,
    ) {
    }

    public function __invoke(string $name, ?string $description): ServiceOverview
    {
        $this->access->require(Action::Create, Resource::Services, refusal: 'services.adminOnlyCreate');

        return ServiceOverview::of(
            $this->services->create($name, $description),
            new ArrayCollection(),
            MemberCounts::none(),
        );
    }
}
