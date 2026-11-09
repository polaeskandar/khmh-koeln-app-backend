<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceChoices
{
    public function __construct(
        private ServiceRepositoryInterface $services,
    ) {
    }

    public function chosen(?string $serviceId, string $field): Service
    {
        $id = $serviceId ?? '';
        $service = Uuid::isValid($id) ? $this->services->find(Uuid::fromString($id)) : null;

        if ($service === null) {
            throw InvalidInput::field($field, 'meetings.chooseService');
        }

        if ($service->isArchived()) {
            throw InvalidInput::field($field, 'meetings.serviceArchived', ['name' => $service->name()->value]);
        }

        return $service;
    }
}
