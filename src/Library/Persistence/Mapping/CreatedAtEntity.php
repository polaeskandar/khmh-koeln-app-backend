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
abstract class CreatedAtEntity
{
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?DateTimeImmutable $createdAt = null;

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? throw new LogicException('The entity is not persisted yet.');
    }

    #[ORM\PrePersist]
    public function stampCreatedAt(): void
    {
        $this->createdAt = Clock::get()->now();
    }
}
