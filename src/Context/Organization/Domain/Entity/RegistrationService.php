<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'registration_service', schema: 'organization')]
final class RegistrationService
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private readonly Uuid $accountId;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'service_id', nullable: false, onDelete: 'CASCADE')]
    private readonly Service $service;

    public function __construct(Uuid $accountId, Service $service)
    {
        $this->accountId = $accountId;
        $this->service = $service;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }

    public function service(): Service
    {
        return $this->service;
    }
}
