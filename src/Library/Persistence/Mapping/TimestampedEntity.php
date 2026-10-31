<?php

declare(strict_types=1);

namespace App\Library\Persistence\Mapping;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Clock\Clock;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class TimestampedEntity extends CreatedAtEntity
{
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?DateTimeImmutable $updatedAt = null;

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? throw new LogicException('The entity is not persisted yet.');
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function stampUpdatedAt(): void
    {
        $this->updatedAt = Clock::get()->now();
    }
}
