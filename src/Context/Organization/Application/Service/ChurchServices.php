<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\Description;
use App\Context\Organization\Domain\ValueObject\ServiceName;
use App\Context\Organization\Domain\ValueObject\Tone;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class ChurchServices
{
    private const int DESCRIPTION_LENGTH = 2000;

    public function __construct(
        private ServiceRepositoryInterface $services,
    ) {
    }

    public function find(string $id): Service
    {
        $service = Uuid::isValid($id) ? $this->services->find(Uuid::fromString($id)) : null;

        return $service ?? throw NotFound::of('service');
    }

    public function create(string $name, ?string $description): Service
    {
        self::check($name, $description);

        $service = new Service(
            ServiceName::of($name),
            self::description($description),
            Tone::leastUsed($this->services->tonesInUse()),
        );

        $this->ensureFree($service->name(), $service);
        $this->save($service);

        return $service;
    }

    public function edit(string $id, string $name, ?string $description): Service
    {
        $service = $this->find($id);
        self::check($name, $description);
        $serviceName = ServiceName::of($name);

        $this->ensureFree($serviceName, $service);
        $service->edit($serviceName, self::description($description));
        $this->save($service);

        return $service;
    }

    private static function check(string $name, ?string $description): void
    {
        InvalidInput::all(
            static fn (): ServiceName => ServiceName::of($name),
            static fn (): Description => self::description($description),
        );
    }

    private static function description(?string $description): Description
    {
        return Description::of($description, max: self::DESCRIPTION_LENGTH);
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
        $holder = $this->services->findByNameKey($name->key) ?? $service;

        if ($holder === $service) {
            return;
        }

        $code = $holder->isArchived() ? 'services.nameTakenArchived' : 'services.nameTaken';

        throw InvalidInput::field('name', $code, ['name' => $holder->name()->value]);
    }
}
