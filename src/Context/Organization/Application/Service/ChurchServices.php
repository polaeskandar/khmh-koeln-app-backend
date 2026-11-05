<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\DescriptionChange;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class ChurchServices
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private StageRepositoryInterface $stages,
        private StructureLookup $structure,
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

    /**
     * @param ReadableCollection<int, Service> $services
     *
     * @return ReadableCollection<int, Stage>
     */
    public function activeStagesIn(ReadableCollection $services): ReadableCollection
    {
        return $this->stages->activeIn($services);
    }

    /**
     * @return ReadableCollection<int, Stage>
     */
    public function activeStagesOf(Service $service): ReadableCollection
    {
        return $this->stages->activeIn(new ArrayCollection([$service]));
    }

    /**
     * @return list<Uuid>
     */
    public function archivedIds(): array
    {
        return $this->structure->archivedIds();
    }

    public function find(string $id): Service
    {
        return $this->services->find(Uuid::fromString($id)) ?? throw NotFound::of('service');
    }

    public function create(string $name, ?string $description): Service
    {
        $service = new Service($name, $description);
        $this->save($service);

        return $service;
    }

    public function edit(string $id, ?string $name, DescriptionChange $description): Service
    {
        $service = $this->find($id);

        $service->edit($name ?? $service->name()->value, $description->appliedTo($service->description()->value));
        $this->save($service);

        return $service;
    }

    private function save(Service $service): void
    {
        $holder = $this->services->findByNameKey($service->name()->key);

        if ($holder !== null && $holder !== $service) {
            throw self::taken($holder);
        }

        try {
            $this->services->save($service);
        } catch (UniqueConstraintViolationException) {
            throw self::taken($service);
        }
    }

    private static function taken(Service $holder): InvalidInput
    {
        $code = $holder->isArchived() ? 'services.nameTakenArchived' : 'services.nameTaken';

        return InvalidInput::field('name', $code, ['name' => $holder->name()->value]);
    }
}
