<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Entity;

use App\Library\Persistence\Mapping\CreatedAtEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'staff_assignment', schema: 'organization')]
#[ORM\UniqueConstraint(
    name: 'staff_assignment_service_unique',
    columns: ['account_id', 'service_id'],
    options: ['where' => '(stage_id IS NULL)'],
)]
#[ORM\UniqueConstraint(
    name: 'staff_assignment_stage_unique',
    columns: ['account_id', 'stage_id'],
    options: ['where' => '(stage_id IS NOT NULL)'],
)]
final class StaffAssignment extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'account_id', type: UuidType::NAME)]
    private readonly Uuid $accountId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'service_id', nullable: false, onDelete: 'CASCADE')]
    private readonly Service $service;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'stage_id', nullable: true, onDelete: 'CASCADE')]
    private readonly ?Stage $stage;

    public function __construct(Uuid $accountId, Service $service, ?Stage $stage)
    {
        $this->id = Uuid::v7();
        $this->accountId = $accountId;
        $this->service = $service;
        $this->stage = $stage;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }

    public function service(): Service
    {
        return $this->service;
    }

    public function stage(): ?Stage
    {
        return $this->stage;
    }
}
