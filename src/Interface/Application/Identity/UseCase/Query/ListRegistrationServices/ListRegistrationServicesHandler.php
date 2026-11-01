<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Query\ListRegistrationServices;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Interface\Application\Identity\Model\ServiceOption;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListRegistrationServicesHandler
{
    public function __construct(
        private ServiceRepositoryInterface $services,
    ) {
    }

    /**
     * @return list<ServiceOption>
     */
    public function __invoke(ListRegistrationServicesQuery $query): array
    {
        $services = new ArrayCollection($this->services->active()->slice(offset: 0, length: $query->limit));

        return $services->map(
            static fn (Service $service): ServiceOption => new ServiceOption(
                $service->id()->toRfc4122(),
                $service->name()->value,
            ),
        )->getValues();
    }
}
