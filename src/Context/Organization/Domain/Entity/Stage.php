<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\ValueObject\Description;
use App\Context\Organization\Domain\ValueObject\StageName;
use App\Library\Persistence\Mapping\CreatedAtEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'stage', schema: 'organization')]
#[ORM\UniqueConstraint(name: 'stage_service_name_key_unique', columns: ['service_id', 'name_key'])]
#[ORM\UniqueConstraint(name: 'stage_id_service_unique', columns: ['id', 'service_id'])]
#[ORM\Index(name: 'stage_service_position_idx', columns: ['service_id', 'position'])]
final class Stage extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private readonly Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'service_id', nullable: false, onDelete: 'RESTRICT')]
    private readonly ChurchService $service;

    #[ORM\Embedded(StageName::class, columnPrefix: false)]
    private StageName $name;

    #[ORM\Embedded(Description::class, columnPrefix: false)]
    private Description $description;

    #[ORM\Column]
    private int $position;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    public function __construct(ChurchService $service, string $name, ?string $description, int $position)
    {
        $this->id = Uuid::v7();
        $this->service = $service;
        $this->name = StageName::of($name);
        $this->description = Description::of($description, max: 500);
        $this->position = $position;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function service(): ChurchService
    {
        return $this->service;
    }

    public function name(): StageName
    {
        return $this->name;
    }

    public function description(): Description
    {
        return $this->description;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function archive(DateTimeImmutable $at): void
    {
        $this->archivedAt = $at;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}
