<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\ValueObject\Description;
use App\Context\Organization\Domain\ValueObject\ServiceName;
use App\Context\Organization\Domain\ValueObject\Tone;
use App\Library\Persistence\Mapping\TimestampedEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'service', schema: 'organization')]
#[ORM\UniqueConstraint(name: 'service_name_key_unique', columns: ['name_key'])]
final class Service extends TimestampedEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Embedded(ServiceName::class, columnPrefix: false)]
    private ServiceName $name;

    #[ORM\Embedded(Description::class, columnPrefix: false)]
    private Description $description;

    #[ORM\Embedded(Tone::class, columnPrefix: false)]
    private Tone $tone;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    public function __construct(ServiceName $name, Description $description, Tone $tone)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->description = $description;
        $this->tone = $tone;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): ServiceName
    {
        return $this->name;
    }

    public function description(): Description
    {
        return $this->description;
    }

    public function tone(): Tone
    {
        return $this->tone;
    }

    public function edit(ServiceName $name, Description $description): void
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function archivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function archive(DateTimeImmutable $at): void
    {
        $this->archivedAt = $at;
    }

    public function restore(): void
    {
        $this->archivedAt = null;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}
