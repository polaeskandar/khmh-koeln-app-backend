<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Organization\Application\Service\RegistrationServices;
use App\Context\Organization\Domain\Entity\Service;
use App\Workflow\Identity\Model\ServiceOption;

final readonly class ListRegistrationServicesWorkflow
{
    private const int LIMIT = 200;

    public function __construct(
        private RegistrationServices $services,
    ) {
    }

    /**
     * @return list<ServiceOption>
     */
    public function __invoke(): array
    {
        return $this->services
            ->listActive(self::LIMIT)
            ->map(
                static fn (Service $service): ServiceOption => new ServiceOption(
                    $service->id()->toRfc4122(),
                    $service->name()->value,
                ),
            )
            ->getValues();
    }
}
