<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\ServiceName;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class ChurchServices
{
    public function __construct(
        private ServiceRepositoryInterface $services,
    ) {
    }

    /**
     * @return ReadableCollection<int, Service>
     */
    public function listed(string $status): ReadableCollection
    {
        $filter = ServiceFilter::tryFrom($status) ?? throw InvalidInput::field('status', 'validation.invalidChoice');

        return $this->services->listed($filter);
    }

    public function find(string $id): Service
    {
        $service = Uuid::isValid($id) ? $this->services->find(Uuid::fromString($id)) : null;

        return $service ?? throw NotFound::of('service');
    }

    public function create(string $name, ?string $description): Service
    {
        $service = new Service($name, $description);

        $this->ensureFree($service->name(), $service);
        $this->save($service);

        return $service;
    }

    public function edit(string $id, string $name, ?string $description): Service
    {
        $service = $this->find($id);
        [$serviceName, $serviceDescription] = Service::details($name, $description);

        $this->ensureFree($serviceName, $service);
        $service->edit($serviceName, $serviceDescription);
        $this->save($service);

        return $service;
    }

    private function save(Service $service): void
    {
        try {
            $this->services->save($service);
        } catch (UniqueConstraintViolationException $violation) {
            $this->ensureFree($service->name(), $service);

            throw $violation;
        }
    }

    private function ensureFree(ServiceName $name, Service $service): void
    {
        $holder = $this->services->findByNameKey($name->key);

        if ($holder === null || $holder === $service) {
            return;
        }

        throw $holder->nameTaken();
    }
}
