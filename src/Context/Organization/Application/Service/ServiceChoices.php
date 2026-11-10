<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;

final readonly class ServiceChoices
{
    public function __construct(
        private ServiceRepositoryInterface $services,
    ) {
    }

    public function of(?string $serviceId, string $field): Service
    {
        $id = Ids::toUuidOrNull($serviceId);
        $service = $id === null ? null : $this->services->find($id);

        if ($service === null) {
            throw InvalidInput::field($field, 'meetings.chooseService');
        }

        if ($service->isArchived()) {
            throw InvalidInput::field($field, 'meetings.serviceArchived', ['name' => $service->name()->value]);
        }

        return $service;
    }
}
