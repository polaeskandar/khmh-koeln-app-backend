<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Repository\PermissionRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrinePermissionRepository implements PermissionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function catalog(): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Permission::class)->findAll());
    }
}
