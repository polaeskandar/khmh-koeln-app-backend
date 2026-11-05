<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Entity;

use App\Context\People\Domain\ValueObject\Tenure;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'membership', schema: 'people')]
#[ORM\UniqueConstraint(
    name: 'membership_open_stage_unique',
    columns: ['person_id', 'stage_id'],
    options: ['where' => '((ends_on IS NULL) AND (stage_id IS NOT NULL))'],
)]
#[ORM\UniqueConstraint(
    name: 'membership_open_service_unique',
    columns: ['person_id', 'service_id'],
    options: ['where' => '((ends_on IS NULL) AND (stage_id IS NULL))'],
)]
#[ORM\Index(name: 'membership_roster_idx', columns: ['service_id', 'stage_id'])]
final class Membership
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'person_id', nullable: false, onDelete: 'RESTRICT')]
    private readonly Person $person;

    #[ORM\Column(type: UuidType::NAME)]
    private readonly Uuid $serviceId;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $stageId;

    #[ORM\Embedded(Tenure::class, columnPrefix: false)]
    private Tenure $tenure;

    public function __construct(Person $person, Uuid $serviceId, ?Uuid $stageId, Tenure $tenure)
    {
        $this->id = Uuid::v7();
        $this->person = $person;
        $this->serviceId = $serviceId;
        $this->stageId = $stageId;
        $this->tenure = $tenure;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function person(): Person
    {
        return $this->person;
    }

    public function serviceId(): Uuid
    {
        return $this->serviceId;
    }

    public function stageId(): ?Uuid
    {
        return $this->stageId;
    }

    public function tenure(): Tenure
    {
        return $this->tenure;
    }

    public function changeTenure(Tenure $tenure): void
    {
        $this->tenure = $tenure;
    }

    public function changeStage(?Uuid $stageId): void
    {
        $this->stageId = $stageId;
    }
}
