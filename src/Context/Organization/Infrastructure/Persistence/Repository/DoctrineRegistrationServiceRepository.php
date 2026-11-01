<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\RegistrationService;
use App\Context\Organization\Domain\Repository\RegistrationServiceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineRegistrationServiceRepository implements RegistrationServiceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function add(RegistrationService $registrationService): void
    {
        $this->entityManager->persist($registrationService);
        $this->entityManager->flush();
    }
}
